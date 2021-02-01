<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Gw\AutoCustomerGroup\Helper\AutoCustomerGroup;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractTaxScheme
{
    const XML_PATH_EU_COUNTRIES_LIST = 'general/country/eu_countries';

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

    /**
     * Check whether specified country is in EU countries list
     *
     * @param string $countryCode
     * @param null|int $storeId
     * @return bool
     */
    protected function isCountryInEU($countryCode, $storeId = null)
    {
        $euCountries = explode(
            ',',
            $this->scopeConfig->getValue(self::XML_PATH_EU_COUNTRIES_LIST, ScopeInterface::SCOPE_STORE, $storeId)
        );
        return in_array($countryCode, $euCountries);
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
     * Check whether a validation result contained a valid VAT Number
     *
     * @param DataObject $validationResult
     * @return boolean
     */
    protected function isValid($validationResult)
    {
        return ($validationResult->getRequestSuccess() && $validationResult->getIsValid());
    }

    abstract public function checkCountry($country);
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
