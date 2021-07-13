<?php
namespace Gw\AutoCustomerGroup\Model\ResourceModel;

class OrderTaxScheme extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    protected function _construct()
    {
        $this->_init('sales_order_tax_scheme', 'order_tax_scheme_id');
    }
}
