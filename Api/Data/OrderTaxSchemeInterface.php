<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface OrderTaxSchemeInterface
{
    /**
     * @return mixed
     */
    public function save();

    /**
     * @return int
     */
    public function getOrderId(): int;

    /**
     * @param int $orderId
     */
    public function setOrderId(int $orderId);

    /**
     * @return string
     */
    public function getReference(): string;

    /**
     * @param string $reference
     */
    public function setReference(string $reference);

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param string $name
     */
    public function setName(string $name);

    /**
     * @return string
     */
    public function getStoreCurrency(): string;

    /**
     * @param string $currency
     */
    public function setStoreCurrency(string $currency);

    /**
     * @return string
     */
    public function getBaseCurrency(): string;

    /**
     * @param string $currency
     */
    public function setBaseCurrency(string $currency);

    /**
     * @return string
     */
    public function getSchemeCurrency(): string;

    /**
     * @param string $currency
     */
    public function setSchemeCurrency(string $currency);

    /**
     * @return float
     */
    public function getExchangeRateBaseToStore(): float;

    /**
     * @param float $rate
     */
    public function setExchangeRateBaseToStore(float $rate);

    /**
     * @return float
     */
    public function getExchangeRateSchemeToBase(): float;

    /**
     * @param float $rate
     */
    public function setExchangeRateSchemeToBase(float $rate);

    /**
     * @return float
     */
    public function getImportThresholdStore(): float;

    /**
     * @param float $threshold
     */
    public function setImportThresholdStore(float $threshold);

    /**
     * @return float
     */
    public function getImportThresholdBase(): float;

    /**
     * @param float $threshold
     */
    public function setImportThresholdBase(float $threshold);
    /**
     * @return float
     */
    public function getImportThresholdScheme(): float;

    /**
     * @param float $threshold
     */
    public function setImportThresholdScheme(float $threshold);
}
