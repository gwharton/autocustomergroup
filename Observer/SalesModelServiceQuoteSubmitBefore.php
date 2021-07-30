<?php
namespace Gw\AutoCustomerGroup\Observer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Actually perform the group change on the quote as it is now being submitted.
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class SalesModelServiceQuoteSubmitBefore implements ObserverInterface
{
    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @param Session $customerSession
     * @param LoggerInterface $logger
     * @param AutoCustomerGroup $autoCustomerGroup
     */
    public function __construct(
        Session $customerSession,
        LoggerInterface $logger,
        AutoCustomerGroup $autoCustomerGroup
    ) {
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->autoCustomerGroup = $autoCustomerGroup;
    }

    /**
     * Observer for sales_model_service_quote_submit_before
     *
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getData('quote');
        /** @var Order $order */
        $order = $observer->getData('order');

        if (!$this->autoCustomerGroup->isModuleEnabled($quote->getStoreId()) ||
            $quote->getCustomer()->getDisableAutoGroupChange() ||
            !$quote->getItemsCount()) {
            return;
        }

        $extensionAttr = $quote->getExtensionAttributes();
        $newGroup = null;
        if ($extensionAttr && $extensionAttr->getAutocustomergroupNewId()) {
            $newGroup = $extensionAttr->getAutocustomergroupNewId();
        }
        if ($newGroup && $newGroup != $quote->getCustomerGroupId()) {
            $this->customerSession->setCustomerGroupId($newGroup);
            $quoteCustomer = $quote->getCustomer();
            if ($quoteCustomer && $quoteCustomer->getId() !== null) {
                $quoteCustomer->setGroupId($newGroup);
                $quote->setCustomer($quoteCustomer);
            }
            $quote->setCustomerGroupId($newGroup);
            $order->setCustomerGroupId($newGroup);
            $this->logger->info(
                "Gw/AutoCustomerGroup/Observer/SalesModelServiceQuoteSubmitBefore::execute() : " .
                "Overriding Quote and Order CustomerGroupId just before order is placed. Adjusting to " .
                $newGroup
            );
        }
    }
}
