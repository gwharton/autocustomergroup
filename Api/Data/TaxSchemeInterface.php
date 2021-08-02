<?php
namespace Gw\AutoCustomerGroup\Api\Data;

use Magento\Directory\Model\Currency;
use Magento\Quote\Model\Quote;

interface TaxSchemeInterface
{
    public function getSchemeName(): string;
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface;
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        GatewayResponseInterface $vatValidationResult,
        Quote $quote,
        ?int $storeId
    ): ?int;
    public function getFrontEndPrompt(?int $storeId): ?string;
    public function getSchemeCurrencyCode(): string;
    public function getSchemeCurrency(): Currency;
    public function getThresholdInSchemeCurrency(?int $storeId): float;
    public function getThresholdInBaseCurrency(?int $storeId): float;
    public function getSchemeRegistrationNumber(?int $storeId): ?string;
    public function getSchemeId(): string;
    public function __toString(): string;
    public function isSchemeCountry(string $countryId): bool;
    public function getSchemeCountries(): array;
    public function isEnabled(?int $storeId): bool;
    public function getSchemeExchangeRate(?int $storeId): float;
}
