<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Gw\AutoCustomerGroup\Helper\AutoCustomerGroup;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTaxScheme
{
    const XML_PATH_EU_COUNTRIES_LIST = 'general/country/eu_countries';

    protected $code;
    protected $schemeCountries = [];

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
     * Check if this Tax Scheme is enabled in Config
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
     * Return an array of Country ID's that this Scheme Supports
     *
     * @return string[]
     */
    public function getSchemeCountries()
    {
        return $this->schemeCountries;
    }

    /**
     * Check if this scheme supports the given country
     *
     * @param string $countryId
     * @return string[]
     */
    public function isSchemeCountry($countryId)
    {
        return in_array($countryId, $this->schemeCountries);
    }

    /**
     * Get order total, including discounts
     *
     * @param Quote $quote
     * @return float
     */
    protected function getOrderTotal($quote)
    {
        $orderTotal = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $orderTotal += ($item->getRowTotal() - $item->getDiscountAmount());
        }
        return $orderTotal;
    }

    /**
     * Get most expensive item in order, including any discounts
     *
     * @param Quote $quote
     * @return float
     */
    protected function getMostExpensiveItem($quote)
    {
        $mostExpensive = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $itemPrice = $item->getPrice() - ($item->getDiscountAmount() / $item->getQty());
            if ($itemPrice > $mostExpensive) {
                $mostExpensive = $itemPrice;
            }
        }
        return $mostExpensive;
    }

    /**
     * Check whether a validation result contained a valid VAT Number
     *
     * @param DataObject $validationResult
     * @return boolean
     */
    protected function isValid($validationResult)
    {
        return ($validationResult->getRequestSuccess() && $validationResult->getIsValid());
    }

    abstract public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $vatValidationResult,
        $quote,
        $store = null
    );
    abstract public function checkTaxId(
        $countryCode,
        $taxId
    );
}
