<?php

namespace Gw\AutoCustomerGroup\Plugin\Quote;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Framework\Event\Observer;
use Magento\Quote\Observer\Frontend\Quote\Address\CollectTotalsObserver;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Disable the builtin CollectTotals Observer which performs VAT validation and group change.
 * If Autocustomergroup module is disabled for this store, then run existing observer
 */
class DisableCollectTotalsObserverPlugin
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
     * Disable Collect Totals Observer
     *
     * @param CollectTotalsObserver $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        CollectTotalsObserver $subject,
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
