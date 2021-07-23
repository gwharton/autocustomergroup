<?php
namespace Gw\AutoCustomerGroup\Model\Collector;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
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

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    public function __construct(
        \Gw\AutoCustomerGroup\Model\AutoCustomerGroup $autoCustomerGroup,
        Session $customerSession,
        LoggerInterface $logger,
        AddressRepositoryInterface $addressRepository,
        array $additionalCollectors = []
    ) {
        $this->setCode('autocustomergroup');
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->addressRepository = $addressRepository;
        $this->additionalCollectors = $additionalCollectors;
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     * @throws LocalizedException
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

        if (!$this->autoCustomerGroup->isModuleEnabled($storeId) ||
            $customer->getDisableAutoGroupChange() ||
            !$quote->getItemsCount()) {
            return $this;
        }

        $address = $quote->getShippingAddress();
        $customerCountryCode = $address->getCountryId();
        $customerTaxId = $address->getVatId();
        $customerGroupId = $customer->getGroupId() ?: $this->autoCustomerGroup->getDefaultGroup($storeId);

        // try to get data from customer if quote address needed data is empty
        if (empty($customerCountryCode) && empty($customerTaxId) && $customer->getDefaultShipping()) {
            $customerAddress = $this->addressRepository->getById($customer->getDefaultShipping());
            $customerCountryCode = $customerAddress->getCountryId();
            $customerTaxId = $customerAddress->getVatId();
            $address->setCountryId($customerCountryCode);
            $address->setVatId($customerTaxId);
        }

        // We can't proceed if we dont have a country code or a store ID.
        if (empty($customerCountryCode) || !$storeId) {
            return $this;
        }

        if (!empty($customerTaxId)) {
            //We have a tax ID

            //If we are set to not validate on each transaction and the country code and tax ID match that saved
            //in the customer address, just reuse.
            if (!$this->autoCustomerGroup->isValidateOnEachTransactionEnabled($storeId) &&
                $customerCountryCode == $address->getData('validated_country_code') &&
                $customerTaxId != $address->getData('validated_vat_number')
            ) {
                $validationResult = new DataObject(
                    [
                        'is_valid' => (int)$address->getData('vat_is_valid'),
                        'request_identifier' => (string)$address->getData('vat_request_id'),
                        'request_date' => (string)$address->getData('vat_request_date'),
                        'request_success' => (bool)$address->getData('vat_request_success'),
                    ]
                );
            } else {
                //We have a Tax ID and we need to validate online
                $validationResult = $this->autoCustomerGroup->checkTaxId(
                    $customerCountryCode,
                    $customerTaxId,
                    $storeId
                );
                if ($validationResult) {
                    // Store validation results in corresponding quote address
                    $address->setData('vat_is_valid', $validationResult->getData('is_valid'));
                    $address->setData('vat_request_id', $validationResult->getData('request_identifier'));
                    $address->setData('vat_request_date', $validationResult->getData('request_date'));
                    $address->setData('vat_request_success', $validationResult->getData('request_success'));
                    $address->setData('validated_vat_number', $customerTaxId);
                    $address->setData('validated_country_code', $customerCountryCode);
                    $quote->setShippingAddress($address);
                }
            }
        } else {
            //Tax ID is empty
            $validationResult = new DataObject(
                [
                    'is_valid' => false,
                    'request_identifier' => '',
                    'request_date' => '',
                    'request_success' => false,
                ]
            );
        }

        //Get the auto assigned group for customer, returns null if group shouldnt be changed.
        $newGroup = $this->autoCustomerGroup->getCustomerGroup(
            $customerCountryCode,
            $address->getPostcode() ?: "",
            $validationResult,
            $quote,
            $storeId
        );

        $this->logger->debug(
            "AutoCustomerGroup::Collector/AutoCustomerGroup::collect() - Quote Group Id=" .
            $quote->getCustomerGroupId() .
            " Customer Group Id=" .
            $customerGroupId .
            " New Group Id=" .
            $newGroup
        );

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
    private function updateGroup($newGroup, $quote, $customer, $shippingAssignment, $total)
    {
        if ($newGroup != $quote->getCustomerGroupId()) {
            $this->customerSession->setCustomerGroupId($newGroup);
            $customer = $quote->getCustomer();
            if ($customer && $customer->getId() !== null) {
                $customer->setGroupId($newGroup);
                $quote->setCustomer($customer);
            }
            $this->logger->debug(
                "AutoCustomerGroup::Collector/AutoCustomerGroup::updateGroup() - Setting quote Group to " .
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
