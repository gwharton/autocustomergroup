<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface OrderTaxSchemeInterface
{
    /**
     * @return $this
     */
    public function save();

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     * @return void
     */
    public function setOrderId(int $orderId): void;

    /**
     * @return string|null
     */
    public function getReference(): ?string;

    /**
     * @param string|null $reference
     * @return void
     */
    public function setReference(?string $reference): void;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string|null $name
     * @return void
     */
    public function setName(?string $name): void;

    /**
     * @return string|null
     */
    public function getStoreCurrency(): ?string;

    /**
     * @param string|null $currency
     * @return void
     */
    public function setStoreCurrency(?string $currency): void;

    /**
     * @return string|null
     */
    public function getBaseCurrency(): ?string;

    /**
     * @param string|null $currency
     * @return void
     */
    public function setBaseCurrency(?string $currency): void;

    /**
     * @return string|null
     */
    public function getSchemeCurrency(): ?string;

    /**
     * @param string|null $currency
     * @return void
     */
    public function setSchemeCurrency(?string $currency): void;

    /**
     * @return float
     */
    public function getExchangeRateBaseToStore(): float;

    /**
     * @param float $rate
     * @return void
     */
    public function setExchangeRateBaseToStore(float $rate): void;

    /**
     * @return float
     */
    public function getExchangeRateSchemeToBase(): float;

    /**
     * @param float $rate
     * @return void
     */
    public function setExchangeRateSchemeToBase(float $rate): void;

    /**
     * @return float
     */
    public function getImportThresholdStore(): float;

    /**
     * @param float $threshold
     * @return void
     */
    public function setImportThresholdStore(float $threshold): void;

    /**
     * @return float
     */
    public function getImportThresholdBase(): float;

    /**
     * @param float $threshold
     * @return void
     */
    public function setImportThresholdBase(float $threshold): void;

    /**
     * @return float
     */
    public function getImportThresholdScheme(): float;

    /**
     * @param float $threshold
     * @return void
     */
    public function setImportThresholdScheme(float $threshold): void;
}
