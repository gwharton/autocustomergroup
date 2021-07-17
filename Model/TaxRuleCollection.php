<?php

namespace Gw\AutoCustomerGroup\Model;

use Magento\Framework\DataObject;
use Magento\Tax\Api\Data\TaxRuleInterface;

/**
 * TaxRuleCollection is used by the Tax Rule Admin Grid. We add the
 * tax_scheme_id data item so it can be displayed in the grid.
 */
class TaxRuleCollection extends \Magento\Tax\Model\TaxRuleCollection
{
    /**
     * @param TaxRuleInterface $taxRule
     * @return DataObject
     */
    protected function createTaxRuleCollectionItem(TaxRuleInterface $taxRule): DataObject
    {
        $collectionItem = parent::createTaxRuleCollectionItem($taxRule);
        $taxScheme = $taxRule->getExtensionAttributes()->getTaxScheme();
        if ($taxScheme) {
            $collectionItem->setData(
                'extension_attributes',
                $taxRule->getExtensionAttributes()
            );
            $collectionItem->setData('tax_scheme_id', $taxScheme->getSchemeId());
        }
        return $collectionItem;
    }
}
