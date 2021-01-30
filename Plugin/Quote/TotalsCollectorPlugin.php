<?php
namespace Gw\AutoCustomerGroup\Plugin\Quote;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Customer\Model\Session;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\TotalsCollector;

class TotalsCollectorPlugin
{
    /**
     * @var AddressHelper
     */
    private $customerAddressHelper;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     */
    public function __construct(
        AddressHelper $customerAddressHelper,
        Session $customerSession,
        AutoCustomerGroup $autoCustomerGroup
    ) {
        $this->customerAddressHelper = $customerAddressHelper;
        $this->customerSession = $customerSession;
        $this->autoCustomerGroup = $autoCustomerGroup;
    }

    /**
     * @param TotalsCollector $subject
     * @param Total $total
     * @param Quote $quote
     * @return Total
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCollect(
        TotalsCollector $subject,
        Total $total,
        Quote $quote
    ) {
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $quote->getCustomer();
        $storeId = $customer->getStoreId();

        if ($customer->getDisableAutoGroupChange() ||
            !$this->customerAddressHelper->isVatValidationEnabled($storeId) ||
            !$quote->getItemsCount()) {
            return $total;
        }

        $address = $quote->getShippingAddress();
        $customerCountryCode = $address->getCountryId();
        $customerTaxId = $address->getVatId();

        /** try to get data from customer if quote address needed data is empty */
        if (empty($customerCountryCode) && empty($customerTaxId) && $customer->getDefaultShipping()) {
            $customerAddress = $this->addressRepository->getById($customer->getDefaultShipping());
            $customerCountryCode = $customerAddress->getCountryId();
            $customerTaxId = $customerAddress->getVatId();
            $address->setCountryId($customerCountryCode);
            $address->setVatId($customerTaxId);
        }

        $validationResult = null;
        if (!empty($customerTaxId) &&
            ($this->customerAddressHelper->hasValidateOnEachTransaction($storeId) ||
            $customerCountryCode != $address->getValidatedCountryCode() ||
            $customerTaxId != $address->getValidatedVatNumber())
        ) {
            $validationResult = $this->autoCustomerGroup->checkTaxId(
                $customerCountryCode,
                $customerTaxId
            );

            // Store validation results in corresponding quote address
            $address->setVatIsValid((int)$validationResult->getIsValid());
            $address->setVatRequestId($validationResult->getRequestIdentifier());
            $address->setVatRequestDate($validationResult->getRequestDate());
            $address->setVatRequestSuccess($validationResult->getRequestSuccess());
            $address->setValidatedVatNumber($customerTaxId);
            $address->setValidatedCountryCode($customerCountryCode);
            $address->save();
        } else {
            // Restore validation results from corresponding quote address
            $validationResult = new DataObject(
                [
                    'is_valid' => (int)$address->getVatIsValid(),
                    'request_identifier' => (string)$address->getVatRequestId(),
                    'request_date' => (string)$address->getVatRequestDate(),
                    'request_success' => (bool)$address->getVatRequestSuccess(),
                ]
            );
        }

        //Get the auto assigned group for customer
        $groupId = $this->autoCustomerGroup->getCustomerGroup(
            $customerCountryCode,
            $address->getPostcode(),
            $validationResult,
            $quote,
            $storeId
        );

        //Update the quote object if the group has changed.
        if ($groupId && $groupId !== $quote->getCustomerGroupId()) {
            $address->setPrevQuoteCustomerGroupId($quote->getCustomerGroupId());
            $quote->setCustomerGroupId($groupId);
            $this->customerSession->setCustomerGroupId($groupId);
            if ($customer->getId() !== null) {
                $customer->setGroupId($groupId);
                $quote->setCustomer($customer);
            }
            //TODO Do we need to re-run collectTotals now we have changed group. What if different discounts
            //apply to different groups?
        }

        return $total;
    }
}
