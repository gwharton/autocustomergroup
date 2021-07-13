<?php
namespace Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Gw\AutoCustomerGroup\Model\OrderTaxScheme::class,
            \Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme::class
        );
    }

    /**
     * Retrieve order tax scheme collection by order identifier
     *
     * @param \Magento\Framework\DataObject $order
     * @return \Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme\Collection
     */
    public function loadByOrder($order)
    {
        $orderId = $order->getId();
        $this->getSelect()->where('main_table.order_id = ?', (int)$orderId);
        return $this->load();
    }
}
