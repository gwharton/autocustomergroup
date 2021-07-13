<?php
namespace Gw\AutoCustomerGroup\Model;

class OrderTaxScheme extends \Magento\Framework\Model\AbstractExtensibleModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme::class);
    }
}
