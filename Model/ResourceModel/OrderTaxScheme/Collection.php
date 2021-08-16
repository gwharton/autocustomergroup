<?php
namespace Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme;

use Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme as OrderTaxSchemeResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Gw\AutoCustomerGroup\Model\OrderTaxScheme;
use Magento\Sales\Api\Data\OrderInterface;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            OrderTaxScheme::class,
            OrderTaxSchemeResource::class
        );
    }

    /**
     * Retrieve order tax scheme collection by order
     *
     * @param OrderInterface $order
     * @return Collection
     */
    public function loadByOrder(OrderInterface $order): Collection
    {
        $orderId = $order->getId();
        $this->getSelect()->where('main_table.order_id = ?', (int)$orderId);
        return $this->load();
    }

    /**
     * Retrieve order tax scheme collection by order identifier
     *
     * @param int $orderId
     * @return Collection
     */
    public function loadByOrderId(int $orderId): Collection
    {
        $this->getSelect()->where('main_table.order_id = ?', $orderId);
        return $this->load();
    }
}
