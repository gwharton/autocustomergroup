<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterfaceFactory;
use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Magento\Directory\Model\Currency;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
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
     * @var GatewayResponseInterfaceFactory
     */
    protected $gwrFactory;

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
     * @param GatewayResponseInterfaceFactory $gwrFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        DateTime $datetime,
        CurrencyFactory $currencyFactory,
        GatewayResponseInterfaceFactory $gwrFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->datetime = $datetime;
        $this->currencyFactory = $currencyFactory;
        $this->gwrFactory = $gwrFactory;
    }

    /**
     * Check if this Tax Scheme is enabled in Config
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId): bool
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
    public function __toString(): string
    {
        return $this->getSchemeId();
    }

    /**
     * Return an array of Country ID's that this Scheme Supports
     *
     * @return string[]
     */
    public function getSchemeCountries(): array
    {
        return $this->schemeCountries;
    }

    /**
     * Check if this scheme supports the given country
     *
     * @param string $countryId
     * @return bool
     */
    public function isSchemeCountry(string $countryId): bool
    {
        return in_array($countryId, $this->schemeCountries);
    }

    /**
     * Get Scheme Code
     *
     * @return string
     */
    public function getSchemeId(): string
    {
        return $this->code;
    }

    /**
     * Get order total, including discounts, in Base Currency
     *
     * @param Quote $quote
     * @return float
     */
    protected function getOrderTotalBaseCurrency(Quote $quote): float
    {
        $orderTotal = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $orderTotal += ($item->getBaseRowTotal() - $item->getBaseDiscountAmount());
        }
        return $orderTotal;
    }

    /**
     * Get most expensive item in order, including any discounts, in Base Currency
     *
     * @param Quote $quote
     * @return float
     */
    protected function getMostExpensiveItemBaseCurrency(Quote $quote)
    {
        $mostExpensive = 0.0;
        foreach ($quote->getItemsCollection() as $item) {
            $itemPrice = $item->getBasePrice() - ($item->getBaseDiscountAmount() / $item->getQty());
            if ($itemPrice > $mostExpensive) {
                $mostExpensive = $itemPrice;
            }
        }
        return $mostExpensive;
    }

    /**
     * Check whether a validation result contained a valid VAT Number
     *
     * @param GatewayResponseInterface $validationResult
     * @return bool
     */
    protected function isValid(GatewayResponseInterface $validationResult): bool
    {
        return ($validationResult->getRequestSuccess() && $validationResult->getIsValid());
    }

    /**
     * Retrieve merchant country code
     *
     * @param int|null $storeId
     * @return string
     */
    protected function getMerchantCountryCode(?int $storeId): string
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
     * @param int|null $storeId
     * @return string|null
     */
    public function getSchemeRegistrationNumber(?int $storeId): ?string
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
     * @param string|null $postCode
     * @return bool
     */
    protected function isNI(string $country, ?string $postCode): bool
    {
        return ($country == "GB" && preg_match("/^[Bb][Tt].*$/", $postCode));
    }

    /**
     * Retrieve merchant Post Code
     *
     * @param int|null $storeId
     * @return string
     */
    protected function getMerchantPostCode(?int $storeId): string
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
    private function useMagentoExchangeRate(?int $storeId): bool
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
    public function getThresholdInBaseCurrency(?int $storeId): float
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
    public function getThresholdInSchemeCurrency(?int $storeId): float
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
    public function getSchemeCurrencyCode(): string
    {
        return static::SCHEME_CURRENCY;
    }

    /**
     * @return Currency
     */
    public function getSchemeCurrency(): Currency
    {
        return $this->currencyFactory->create()->load(static::SCHEME_CURRENCY);
    }

    /**
     * Retrieve Frontend prompt for scheme
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getFrontEndPrompt(?int $storeId): ?string
    {
        return (string)$this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/frontendprompt",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    abstract public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        GatewayResponseInterface $vatValidationResult,
        Quote $quote,
        ?int $storeId
    ): ?int;
    abstract public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface;
    abstract public function getSchemeName(): string;

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate(?int $storeId): float
    {
        if ($this->useMagentoExchangeRate($storeId)) {
            $websiteBaseCurrency = $this->scopeConfig->getValue(
                Currency::XML_PATH_CURRENCY_BASE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $schemeCurrency = $this->getSchemeCurrency();
            $exchangerate = $this->getSchemeCurrency()->getAnyRate($websiteBaseCurrency);
            if (!$exchangerate) {
                $this->logger->critical(
                    "Gw/AutoCustomerGroup/Model/TaxSchemes/AbstractTaxScheme::getSchemeExchangeRate() : " .
                    "No Magento Exchange Rate configured for " . static::SCHEME_CURRENCY . " to " .
                    $websiteBaseCurrency . ". Using 1.0"
                );
                $exchangerate = 1.0;
            }
            return $exchangerate;
        }
        return $this->scopeConfig->getValue(
            "autocustomergroup/" . $this->getSchemeId() . "/exchangerate",
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
