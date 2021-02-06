<?php

namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Observer\BeforeAddressSaveObserver;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;

class BeforeAddressSaveObserverPlugin
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        AutoCustomerGroup $autoCustomerGroup,
        StoreManagerInterface $storeManager
    ) {
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->storeManager = $storeManager;
    }

    /**
     * Disable Before Address Save Observer
     *
     * @param BeforeAddressSaveObserver $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        BeforeAddressSaveObserver $subject,
        callable $proceed,
        Observer $observer
    ) {
        $returnValue = null;
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->autoCustomerGroup->isModuleEnabled($storeId)) {
            $returnValue = $proceed($observer);
        } else {
            /** Magento\Customer\Model\Address $customerAddress */
            $customerAddress = $observer->getCustomerAddress();
            $customer = $customerAddress->getCustomer();

            if ($customerAddress->getShouldIgnoreValidation()) {
                return $returnValue;
            }

            //We only validate the VAT Number and store the results. We do not change the
            //Customer group at this stage, as the this depends on order value, which we
            //Don't have at this stage.
            if (!empty($customerAddress->getVatId())) {
                $validationResult = $this->autoCustomerGroup->checkTaxId(
                    $customerAddress->getCountryId(),
                    $customerAddress->getVatId(),
                    $customer->getStore()->getId()
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
        return $returnValue;
    }
}
