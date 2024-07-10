<?php
namespace Gw\AutoCustomerGroup\Api\Data;

use Magento\Directory\Model\Currency;
use Magento\Quote\Model\Quote;

interface TaxSchemeInterface
{
    /**
     * @return string
     */
    public function getSchemeName(): string;

    /**
     * @param string $countryCode
     * @param string|null $taxId
     * @return GatewayResponseInterface
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface;

    /**
     * @param string $customerCountryCode
     * @param string|null $customerPostCode
     * @param GatewayResponseInterface $vatValidationResult
     * @param Quote $quote
     * @param int|null $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        GatewayResponseInterface $vatValidationResult,
        Quote $quote,
        ?int $storeId
    ): ?int;

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getFrontEndPrompt(?int $storeId): ?string;

    /**
     * @return string
     */
    public function getSchemeCurrencyCode(): string;

    /**
     * @return Currency
     */
    public function getSchemeCurrency(): Currency;

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInSchemeCurrency(?int $storeId): float;

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getThresholdInBaseCurrency(?int $storeId): float;

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSchemeRegistrationNumber(?int $storeId): ?string;

    /**
     * @return string
     */
    public function getSchemeId(): string;

    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @param string $countryId
     * @return bool
     */
    public function isSchemeCountry(string $countryId): bool;

    /**
     * @return array
     */
    public function getSchemeCountries(): array;

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId): bool;

    /**
     * @param int|null $storeId
     * @return float
     */
    public function getSchemeExchangeRate(?int $storeId): float;
}
