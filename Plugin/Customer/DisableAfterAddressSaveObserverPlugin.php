<?php

namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Observer\AfterAddressSaveObserver;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;

class DisableAfterAddressSaveObserverPlugin
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
     * Disable After Address Save Observer
     *
     * @param AfterAddressSaveObserver $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        AfterAddressSaveObserver $subject,
        callable $proceed,
        Observer $observer
    ) {
        $returnValue = null;
        $storeId = $this->storeManager->getStore()->getId();
        if (!$this->autoCustomerGroup->isModuleEnabled($storeId)) {
            $returnValue = $proceed($observer);
        }
        return $returnValue;
    }
}
