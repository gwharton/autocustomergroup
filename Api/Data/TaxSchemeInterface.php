<?php
namespace Gw\AutoCustomerGroup\Api\Data;

use Magento\Directory\Model\Currency;

interface TaxSchemeInterface
{
    /**
     * Get the Scheme Name
     *
     * @return string
     */
    public function getSchemeName();

    /**
     * Check the status of the tax id
     *
     * @param string $countryCode
     * @param string $taxId
     * @return \Magento\Framework\DataObject
     */
    public function checkTaxId($countryCode, $taxId);

    /**
     * Get the correct Customer Group
     *
     * @param string $customerCountryCode
     * @param string $customerPostCode
     * @param \Magento\Framework\DataObject $vatValidationResult
     * @param \Magento\Quote\Api\Data\QuoteInterface $quote
     * @param int|null $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $vatValidationResult,
        $quote,
        $storeId
    );

    /**
     * Get the frontend prompt
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFrontEndPrompt($storeId);

    /**
     * Get the Scheme Currency Code
     *
     * @return string
     */
    public function getSchemeCurrencyCode();

    /**
     * Get the Scheme Currency
     *
     * @return Currency
     */
    public function getSchemeCurrency();

    /**
     * Get the value of the import threshold in Scheme Currency
     *
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency($storeId);

    /**
     * Get the value of the import threshold in Store Base Currency
     *
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInBaseCurrency($storeId);

    /**
     * Get the Scheme Registration Number
     *
     * @param int|null $storeId
     * @return string
     */
    public function getSchemeRegistrationNumber($storeId);

    /**
     * Get the Scheme ID code
     *
     * @return string
     */
    public function getSchemeId();

    /**
     * Returns the Tax Scheme code. Can be used in comparing tax schemes, e.g
     * when performing array_unique.
     *
     * @return string
     */
    public function __toString();

    /**
     * Does this Scheme handle this country
     *
     * @param string $countryId
     * @return boolean
     */
    public function isSchemeCountry($countryId);

    /**
     * Return the list of countries that this scheme supports
     *
     * @return string[]
     */
    public function getSchemeCountries();

    /**
     * Is the scheme enabled for this store
     *
     * @param int|null $storeId
     * @return boolean
     */
    public function isEnabled($storeId);

    /**
     * Get the current Exchange Rate for this scheme
     *
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate($storeId);
}
