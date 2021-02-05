<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTaxScheme
{
    const XML_PATH_EU_COUNTRIES_LIST = 'general/country/eu_countries';
    const SCHEME_CURRENCY = '';

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
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DateTime
     */
    protected $datetime;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param DateTime $datetime
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        DateTime $datetime
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->datetime = $datetime;
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
     * Get Scheme Code
     *
     * @return string
     */
    public function getSchemeId()
    {
        return $this->code;
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

    /**
     * Retrieve merchant country code
     *
     * @param Store|string|int|null $store
     * @return string
     */
    public function getMerchantCountryCode($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check whether specified Country and PostCode is within Northern Ireland
     *
     * @param string $country
     * @param string $postCode
     * @return boolean
     */
    public function isNI($country, $postCode)
    {
        return ($country == "GB" && preg_match("/^[Bb][Tt].*$/", $postCode));
    }

    /**
     * Retrieve merchant Post Code
     *
     * @param Store|string|int|null $store
     * @return string
     */
    public function getMerchantPostCode($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the Import Threshold in Store Currency
     *
     * @param int $storeId
     * @return float
     */
    public function getThresholdInStoreCurrency($storeId = null)
    {
        $importthreshold = $this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $usemagentoexchangerate = $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . $this->getSchemeId() . '/usemagentoexchangerate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($usemagentoexchangerate) {
            return $this->priceCurrency->convert(
                $importthreshold,
                static::SCHEME_CURRENCY,
                $this->storeManager->getStore($storeId)
            );
        } else {
            $exchangerate = $this->scopeConfig->getValue(
                "autocustomergroup/" . $this->getSchemeId() . "/exchangerate",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            return $exchangerate * $importthreshold;
        }
    }

    /**
     * Get the Import Threshold in Scheme Currency
     *
     * @param int $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency($storeId = null)
    {
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the currency of the tax scheme
     *
     * @return string
     */
    public function getSchemeCurrencyCode()
    {
        return static::SCHEME_CURRENCY;
    }

    /**
     * Retrieve Frontend prompt for scheme
     *
     * @param Store|string|int|null $store
     * @return string
     */
    public function getFrontEndPrompt($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/frontendprompt",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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
