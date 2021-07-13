<?php
namespace Gw\AutoCustomerGroup\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme as OrderTaxSchemeResource;

class OrderTaxScheme extends AbstractExtensibleModel
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_init(OrderTaxSchemeResource::class);
    }
}
