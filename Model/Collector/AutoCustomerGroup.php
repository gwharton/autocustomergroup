<?php
namespace Gw\AutoCustomerGroup\Model\Collector;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use Psr\Log\LoggerInterface;

/**
 * AutoCustomerGroup totals collector, configured from sales.xml which runs at the end of the stack
 * of collectors. If the group has changed, then the tax collectors must be re-run as the tax to be
 * applied will now be different. The collectors to be rerun can be configured in di.xml
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class AutoCustomerGroup extends AbstractTotal
{
    /**
     * @var \Gw\AutoCustomerGroup\Model\AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $additionalCollectors;

    public function __construct(
        \Gw\AutoCustomerGroup\Model\AutoCustomerGroup $autoCustomerGroup,
        Session $customerSession,
        LoggerInterface $logger,
        array $additionalCollectors = []
    ) {
        $this->setCode('autocustomergroup');
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->additionalCollectors = $additionalCollectors;
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ): AutoCustomerGroup {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        /** @var CustomerInterface $customer */
        $customer = $quote->getCustomer();
        $storeId = $quote->getStoreId();

        if (!($storeId && $this->autoCustomerGroup->isModuleEnabled($storeId))) {
            return $this;
        }

        if ($customer->getId()) {
            $this->logger->debug(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : Existing Customer Group " .
                $customer->getGroupId()
            );
        }

        if ($customer->getDisableAutoGroupChange()) {
            $this->logger->debug(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : AutoGroupChange disabled " .
                "for customer"
            );
            return $this;
        }

        $quoteAddress = $quote->getShippingAddress();

        if (empty($quoteAddress->getCountryId())) {
            $this->logger->error(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : Quote Country Id empty "
            );
            return $this;
        }
        //If we have a customer, start with their CustomerGroupId, otherwise use default group
        $customerGroupId = $customer->getId() ?
            $customer->getGroupId() :
            $this->autoCustomerGroup->getDefaultGroup($storeId);

        $this->logger->debug(
            "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : Starting Group is " .
            $customerGroupId
        );

        //No point in validating if we haven't got a tax ID
        if (empty($quoteAddress->getVatId())) {
            $validationResult = new DataObject(
                [
                    'is_valid' => false,
                    'request_identifier' => '',
                    'request_date' => '',
                    'request_success' => false,
                ]
            );
        } else {
            if (!$this->autoCustomerGroup->isValidateOnEachTransactionEnabled($storeId) &&
                !empty($quoteAddress->getData('validated_country_code')) &&
                !empty($quoteAddress->getData('validated_vat_number'))
            ) {
                //If we have previous validation data in the address, and we don't have to validate every time
                //Then reuse the validation data
                $this->logger->debug(
                    "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : Reusing validation data " .
                    "from quote address."
                );
                $validationResult = new DataObject(
                    [
                        'is_valid' => (int)$quoteAddress->getData('vat_is_valid'),
                        'request_identifier' => (string)$quoteAddress->getData('vat_request_id'),
                        'request_date' => (string)$quoteAddress->getData('vat_request_date'),
                        'request_success' => (bool)$quoteAddress->getData('vat_request_success'),
                    ]
                );
            } else {
                //Validate every time
                $validationResult = $this->autoCustomerGroup->checkTaxId(
                    $quoteAddress->getCountryId(),
                    $quoteAddress->getVatId(),
                    $storeId
                );
                if ($validationResult) {
                    // Store validation results in corresponding quote address
                    $quoteAddress->setData('vat_is_valid', $validationResult->getData('is_valid'));
                    $quoteAddress->setData('vat_request_id', $validationResult->getData('request_identifier'));
                    $quoteAddress->setData('vat_request_date', $validationResult->getData('request_date'));
                    $quoteAddress->setData('vat_request_success', $validationResult->getData('request_success'));
                    $quoteAddress->setData('validated_vat_number', $quoteAddress->getVatId());
                    $quoteAddress->setData('validated_country_code', $quoteAddress->getCountryId());
                    $quote->setShippingAddress($quoteAddress);
                }
            }
        }

        //Get the auto assigned group for customer, returns null if group shouldnt be changed.
        $newGroup = $this->autoCustomerGroup->getCustomerGroup(
            $quoteAddress->getCountryId(),
            $quoteAddress->getPostcode() ?: "",
            $validationResult,
            $quote,
            $storeId
        );

        if ($newGroup) {
            $this->logger->debug(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : New Group Required " .
                $newGroup
            );
        } else {
            $this->logger->debug(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : No Group Change Required "
            );
        }

        //Set the group of the $quote object, so the collectTotals will be performed on the
        //correct group. Use newGroup if set, otherwise use $customerGroupId
        $this->updateGroup($newGroup ?: $customerGroupId, $quote, $customer, $shippingAssignment, $total);

        //Also store the group in the quote Extension Attribute. We will check in quote submit
        //observer and set group appropriately (Guest orders will reset group to NOT_LOGGED_IN)
        $extensionAttr = $quote->getExtensionAttributes();
        $extensionAttr->setAutocustomergroupNewId($newGroup ?: $customerGroupId);
        $quote->setExtensionAttributes($extensionAttr);

        return $this;
    }

    /**
     * Process Group Change
     * @param $newGroup
     * @param Quote $quote
     * @param $customer
     * @param $shippingAssignment
     * @param $total
     */
    private function updateGroup($newGroup, Quote $quote, $customer, $shippingAssignment, $total)
    {
        if ($newGroup != $quote->getCustomerGroupId()) {
            $this->customerSession->setCustomerGroupId($newGroup);
            $customer = $quote->getCustomer();
            if ($customer && $customer->getId() !== null) {
                $customer->setGroupId($newGroup);
                $quote->setCustomer($customer);
            }
            $this->logger->info(
                "Gw/AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() : Setting quote Group to " .
                $newGroup
            );
            $quote->setCustomerGroupId($newGroup);

            //The group has changed. We need to rerun the Tax collectors.
            foreach ($this->additionalCollectors as $collector) {
                if ($collector) {
                    $collector->collect($quote, $shippingAssignment, $total);
                }
            }
        }
    }
}
