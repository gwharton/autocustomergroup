<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTaxScheme implements TaxSchemeInterface
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
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DateTime
     */
    protected $datetime;

    /**
     * @var CurrencyFactory
     */
    public $currencyFactory;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param DateTime $datetime
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        DateTime $datetime,
        CurrencyFactory $currencyFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->datetime = $datetime;
        $this->currencyFactory = $currencyFactory;
    }

    /**
     * Check if this Tax Scheme is enabled in Config
     *
     * @param int|null $storeId
     * @return boolean
     */
    public function isEnabled($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . $this->code . "/enabled",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Scheme Code
     *
     * @return string
     */
    public function __toString() {
        return $this->getSchemeId();
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
     * Get order total, including discounts, in Base Currency
     *
     * @param Quote $quote
     * @return float
     */
    protected function getOrderTotalBaseCurrency($quote)
    {
        $orderTotal = 0.0;
        $rate = $quote->getBaseToQuoteRate();
        foreach ($quote->getItemsCollection() as $item) {
            $orderTotal += ($item->getBaseRowTotal() - ($item->getDiscountAmount()/$rate));
        }
        return $orderTotal;
    }

    /**
     * Get most expensive item in order, including any discounts, in Base Currency
     *
     * @param Quote $quote
     * @return float
     */
    protected function getMostExpensiveItemBaseCurrency($quote)
    {
        $mostExpensive = 0.0;
        $rate = $quote->getBaseToQuoteRate();
        foreach ($quote->getItemsCollection() as $item) {
            $itemPrice = $item->getBasePrice() - (($item->getDiscountAmount()/$rate) / $item->getQty());
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
     * @param int|null $storeId
     * @return string
     */
    protected function getMerchantCountryCode($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the Scheme Registration number
     *
     * @return string
     */
    public function getSchemeRegistrationNumber($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/registrationnumber",
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
    protected function isNI($country, $postCode)
    {
        return ($country == "GB" && preg_match("/^[Bb][Tt].*$/", $postCode));
    }

    /**
     * Retrieve merchant Post Code
     *
     * @param int|null $storeId
     * @return string
     */
    protected function getMerchantPostCode($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Are we configured to use the Magento Exchange rate or not
     *
     * @param int|null $storeId
     * @return bool
     */
    private function useMagentoExchangeRate($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            "autocustomergroup/" . $this->getSchemeId() . '/usemagentoexchangerate',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the Import Threshold in Base Currency
     *
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInBaseCurrency($storeId)
    {
        return $this->getSchemeExchangeRate($storeId) *
            $this->scopeConfig->getValue(
                "autocustomergroup/" . $this->getSchemeId() . "/importthreshold",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
    }

    /**
     * Get the Import Threshold in Scheme Currency
     *
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency($storeId)
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

    public function getSchemeCurrency()
    {
        return $this->currencyFactory->create()->load(static::SCHEME_CURRENCY);
    }

    /**
     * Retrieve Frontend prompt for scheme
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFrontEndPrompt($storeId)
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
        $storeId
    );
    abstract public function checkTaxId(
        $countryCode,
        $taxId
    );
    abstract public function getSchemeName();

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate($storeId)
    {
        if ($this->useMagentoExchangeRate($storeId)) {
            $websiteBaseCurrency = $this->scopeConfig->getValue(
                Currency::XML_PATH_CURRENCY_BASE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $exchangerate = $this->getSchemeCurrency()->getAnyRate($websiteBaseCurrency);
            if (!$exchangerate) {
                $this->logger->critical("AutoCustomerGroup : No Magento Exchange Rate configured for " .
                    static::SCHEME_CURRENCY . " to " . $websiteBaseCurrency . ". Using 1.0");
                $exchangerate = 1.0;
            }
            return $exchangerate;
        }
        $exchangerate = $this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/exchangerate",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $exchangerate;
    }
}
