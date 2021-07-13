<?php
namespace Gw\AutoCustomerGroup\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderTaxScheme extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('sales_order_tax_scheme', 'order_tax_scheme_id');
    }
}
