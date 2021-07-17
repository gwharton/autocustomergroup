<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Api\Data\OrderTaxSchemeInterface;
use Magento\Framework\Model\AbstractExtensibleModel;
use Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme as OrderTaxSchemeResource;

class OrderTaxScheme extends AbstractExtensibleModel implements OrderTaxSchemeInterface
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(OrderTaxSchemeResource::class);
    }

    public function getOrderId(): int
    {
        return $this->getData('order_id');
    }

    public function setOrderId(int $orderId)
    {
        $this->setData('order_id', $orderId);
    }

    public function getReference(): string
    {
        return $this->getData('reference');
    }

    public function setReference(string $reference)
    {
        $this->setData('reference', $reference);
    }

    public function getName(): string
    {
        return $this->getData('name');
    }

    public function setName(string $name)
    {
        $this->setData('name', $name);
    }

    public function getStoreCurrency(): string
    {
        return $this->getData('store_currency');
    }

    public function setStoreCurrency(string $currency)
    {
        $this->setData('store_currency', $currency);
    }

    public function getBaseCurrency(): string
    {
        return $this->getData('base_currency');
    }

    public function setBaseCurrency(string $currency)
    {
        $this->setData('base_currency', $currency);
    }

    public function getSchemeCurrency(): string
    {
        return $this->getData('scheme_currency');
    }

    public function setSchemeCurrency(string $currency)
    {
        $this->setData('scheme_currency', $currency);
    }

    public function getExchangeRateBaseToStore(): float
    {
        return $this->getData('exchange_rate_base_to_store');
    }

    public function setExchangeRateBaseToStore(float $rate)
    {
        $this->setData('exchange_rate_base_to_store', $rate);
    }

    public function getExchangeRateSchemeToBase(): float
    {
        return $this->getData('exchange_rate_scheme_to_base');
    }

    public function setExchangeRateSchemeToBase(float $rate)
    {
        $this->setData('exchange_rate_scheme_to_base', $rate);
    }

    public function getImportThresholdStore(): float
    {
        return $this->getData('import_threshold_store');
    }

    public function setImportThresholdStore(float $threshold)
    {
        $this->setData('import_threshold_store', $threshold);
    }

    public function getImportThresholdBase(): float
    {
        return $this->getData('import_threshold_base');
    }

    public function setImportThresholdBase(float $threshold)
    {
        $this->setData('import_threshold_base', $threshold);
    }

    public function getImportThresholdScheme(): float
    {
        return $this->getData('import_threshold_scheme');
    }

    public function setImportThresholdScheme(float $threshold)
    {
        $this->setData('import_threshold_scheme', $threshold);
    }
}
