<?php

namespace Gw\AutoCustomerGroup\Plugin\Sales;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Framework\Event\Observer;
use Magento\Sales\Observer\Frontend\RestoreCustomerGroupId;
use Magento\Store\Model\StoreManagerInterface;

class DisableRestoreCustomerGroupIdPlugin
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
     * Disable Restore Customer Group Observer
     *
     * @param RestoreCustomerGroupId $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        RestoreCustomerGroupId $subject,
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
