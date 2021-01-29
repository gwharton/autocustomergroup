<?php
namespace Gw\AutoCustomerGroup\Model\Validator;

use Gw\AutoCustomerGroup\Helper\AutoCustomerGroup;
use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractValidator
{
    protected $code;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var AutoCustomerGroup
     */
    protected $helper;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param AutoCustomerGroup $helper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        AutoCustomerGroup $helper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Check if this Validator is enabled in Config
     *
     * @return boolean
     */
    public function isEnabled($store = null)
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . $this->code . "/enabled",
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param Quote $quote
     * @return void
     */
    protected function getOrderTotal($quote)
    {
        $orderTotal = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $orderTotal += ($item->getRowTotal() - $item->getDiscountAmount());
        }
        return $orderTotal;
    }

    abstract public function checkCountry($country);
    abstract public function getCustomerGroup(
        $customerCountryCode,
        $vatValidationResult,
        $quote,
        $store = null
    );
    abstract public function checkTaxId(
        $countryCode,
        $taxId
    );
}
