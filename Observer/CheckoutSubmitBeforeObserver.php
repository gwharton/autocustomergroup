<?php


namespace Gw\AutoCustomerGroup\Observer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

/**
 * Actually perform the group change on the quote as it is now being submitted.
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class CheckoutSubmitBeforeObserver implements ObserverInterface
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
     * Observer for checkout_submit_before
     *
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute(Observer $observer)
    {
        /** @var Quote $quote */
        $quote = $observer->getData('quote');

        /** @var CustomerInterface $customer */
        $customer = $quote->getCustomer();
        $storeId = $quote->getStoreId();

        if (!$this->autoCustomerGroup->isModuleEnabled($storeId) ||
            $customer->getDisableAutoGroupChange() ||
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
            $customer = $quote->getCustomer();
            if ($customer && $customer->getId() !== null) {
                $customer->setGroupId($newGroup);
                $quote->setCustomer($customer);
            }
            $quote->setCustomerGroupId($newGroup);
            $this->logger->debug(
                "AutoCustomerGroup::CheckoutSubmitBeforeObserver::execute() - Setting quote Group to " .
                $newGroup
            );
        }
    }
}
