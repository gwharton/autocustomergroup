<?php
namespace Gw\AutoCustomerGroup\Plugin\Quote;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\TotalsCollector;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class TotalsCollectorPlugin
{
    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var AddressRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Session $customerSession
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param AddressRepositoryInterface $addressRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $customerSession,
        AutoCustomerGroup $autoCustomerGroup,
        AddressRepositoryInterface $addressRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->addressRepository = $addressRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    /**
     * @param TotalsCollector $subject
     * @param Total $total
     * @param Quote $quote
     * @return Total
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @throws LocalizedException
     * @throws Exception
     */
    public function afterCollect(
        TotalsCollector $subject,
        Total $total,
        Quote $quote
    ): Total {
        /** @var CustomerInterface $customer */
        $customer = $quote->getCustomer();
        $storeId = $quote->getStoreId();

        if (!$this->autoCustomerGroup->isModuleEnabled($storeId) ||
            $customer->getDisableAutoGroupChange() ||
            !$quote->getItemsCount()) {
            return $total;
        }

        $address = $quote->getShippingAddress();
        $customerCountryCode = $address->getCountryId();
        $customerTaxId = $address->getVatId();
        $customerGroupId = $customer->getGroupId() ?? $this->autoCustomerGroup->getDefaultGroup($storeId);

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
            return $total;
        }

        if (!empty($customerTaxId)) {
            //We have a tax ID

            //If we are set to not validate on each transaction and the country code and tax ID match that saved
            //in the customer address, just reuse.
            if (!$this->autoCustomerGroup->isValidateOnEachTransactionEnabled($storeId) &&
                $customerCountryCode == $address->getValidatedCountryCode() &&
                $customerTaxId != $address->getValidatedVatNumber()
            ) {
                $validationResult = new DataObject(
                    [
                        'is_valid' => (int)$address->getVatIsValid(),
                        'request_identifier' => (string)$address->getVatRequestId(),
                        'request_date' => (string)$address->getVatRequestDate(),
                        'request_success' => (bool)$address->getVatRequestSuccess(),
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
                    $address->setVatIsValid($validationResult->getIsValid());
                    $address->setVatRequestId($validationResult->getRequestIdentifier());
                    $address->setVatRequestDate($validationResult->getRequestDate());
                    $address->setVatRequestSuccess($validationResult->getRequestSuccess());
                    $address->setValidatedVatNumber($customerTaxId);
                    $address->setValidatedCountryCode($customerCountryCode);
                    $address->save();
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
            $address->getPostcode() ?? "",
            $validationResult,
            $quote,
            $storeId
        );

        $this->logger->debug(
            "AutoCustomerGroup::Plugin/Quote/TotalsCollectorPlugin.php - Quote Group Id=" .
            $quote->getCustomerGroupId() .
            " Customer Group Id=" .
            $customerGroupId .
            " New Group Id=" .
            $newGroup
        );

        //If $newGroup is set, then a group change is requested by the module. If the new group differs from the
        //existing groupId then update the customer session, customer group and quote customer group.
        if ($newGroup !== null) {
            if ($newGroup != $quote->getCustomerGroupId()) {
                $this->updateGroup($newGroup, $quote, $customer, $address);
            }
        } else {
            // No group change requested.
            // Set quote to $customerGroupId (either existing customers group, or default group)
            $this->updateGroup($customerGroupId, $quote, $customer, $address);
        }
        return $total;
    }

    /**
     * Process Group Change
     * @param $newGroup
     * @param $quote
     * @param $customer
     * @param $address
     */
    private function updateGroup($newGroup, $quote, $customer, $address)
    {
        $this->customerSession->setCustomerGroupId($newGroup);
        if ($customer->getId() !== null) {
            $customer->setGroupId($newGroup);
            $quote->setCustomer($customer);
        }
        $address->setPrevQuoteCustomerGroupId($quote->getCustomerGroupId());
        $quote->setCustomerGroupId($newGroup);
        $this->logger->debug(
            "AutoCustomerGroup::Plugin/Quote/TotalsCollectorPlugin.php::updateGroup() - Setting quote Group to " .
            $newGroup
        );
    }
}
