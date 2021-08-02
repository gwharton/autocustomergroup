<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface OrderTaxSchemeInterface
{
    public function save();

    public function getOrderId(): int;
    public function setOrderId(int $orderId);

    public function getReference(): ?string;
    public function setReference(?string $reference);

    public function getName(): ?string;
    public function setName(?string $name);

    public function getStoreCurrency(): ?string;
    public function setStoreCurrency(?string $currency);

    public function getBaseCurrency(): ?string;
    public function setBaseCurrency(?string $currency);

    public function getSchemeCurrency(): ?string;
    public function setSchemeCurrency(?string $currency);

    public function getExchangeRateBaseToStore(): float;
    public function setExchangeRateBaseToStore(float $rate);

    public function getExchangeRateSchemeToBase(): float;
    public function setExchangeRateSchemeToBase(float $rate);

    public function getImportThresholdStore(): float;
    public function setImportThresholdStore(float $threshold);

    public function getImportThresholdBase(): float;
    public function setImportThresholdBase(float $threshold);

    public function getImportThresholdScheme(): float;
    public function setImportThresholdScheme(float $threshold);
}
