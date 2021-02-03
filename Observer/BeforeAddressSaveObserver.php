<?php

namespace Gw\AutoCustomerGroup\Observer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Helper\Address as HelperAddress;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class BeforeAddressSaveObserver implements ObserverInterface
{
    /**
     * @var HelperAddress
     */
    private $customerAddress;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @param HelperAddress $customerAddress
     * @param AutoCustomerGroup $autoCustomerGroup
     */
    public function __construct(
        HelperAddress $customerAddress,
        AutoCustomerGroup $autoCustomerGroup
    ) {
        $this->customerAddress = $customerAddress;
        $this->autoCustomerGroup = $autoCustomerGroup;
    }

    /**
     * Address after save event handler
     *
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /** Magento\Customer\Model\Address $customerAddress */
        $customerAddress = $observer->getCustomerAddress();
        $customer = $customerAddress->getCustomer();

        if (!$this->customerAddress->isVatValidationEnabled($customer->getStore())
            || $customerAddress->getShouldIgnoreValidation()
        ) {
            return;
        }

        //We only validate the VAT Number and store the results. We do not change the
        //Customer group at this stage, as the this depends on order value, which we
        //Don't have at this stage.
        if (!empty($customerAddress->getVatId())) {
            $validationResult = $this->autoCustomerGroup->checkTaxId(
                $customerAddress->getCountryId(),
                $customerAddress->getVatId()
            );

            if ($validationResult) {
                // Store validation results in corresponding customer address
                //$customerAddress->setVatValidationResult($validationResult);
                $customerAddress->setVatIsValid($validationResult->getIsValid());
                $customerAddress->setVatRequestId($validationResult->getRequestIdentifier());
                $customerAddress->setVatRequestDate($validationResult->getRequestDate());
                $customerAddress->setVatRequestSuccess($validationResult->getRequestSuccess());
            }
        }
    }
}
