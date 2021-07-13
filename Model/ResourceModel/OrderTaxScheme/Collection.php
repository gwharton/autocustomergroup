<?php
namespace Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme;

use Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme as OrderTaxSchemeResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Gw\AutoCustomerGroup\Model\OrderTaxScheme;
use Magento\Framework\DataObject;

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
     * Retrieve order tax scheme collection by order identifier
     *
     * @param DataObject $order
     * @return Collection
     */
    public function loadByOrder($order)
    {
        $orderId = $order->getId();
        $this->getSelect()->where('main_table.order_id = ?', (int)$orderId);
        return $this->load();
    }
}
