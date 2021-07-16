<?php
namespace Gw\AutoCustomerGroup\Model\Source;

use Magento\Framework\Exception\StateException;
use Magento\Tax\Api\TaxClassManagementInterface;
use Magento\Tax\Model\ClassModel;
use Gw\AutoCustomerGroup\Model\TaxSchemes as TaxSchemesModel;

class TaxSchemes extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    /**
     * @var TaxSchemesModel
     */
    protected $taxSchemes;

    /**
     * @param TaxSchemesModel $taxSchemes
     */
    public function __construct(
        TaxSchemesModel $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * @return array
     */
    public function getAllOptions()
    {
        if (empty($this->_options)) {
            $this->_options = $this->taxSchemes->toOptionArray();
        }
        return $this->_options;
    }
}
