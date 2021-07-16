<?php

namespace Gw\AutoCustomerGroup\Model;

use Magento\Tax\Api\Data\TaxRuleInterface;

class TaxRuleCollection extends \Magento\Tax\Model\TaxRuleCollection
{
    protected function createTaxRuleCollectionItem(TaxRuleInterface $taxRule)
    {
        $collectionItem = parent::createTaxRuleCollectionItem($taxRule);
        $taxScheme = $taxRule->getExtensionAttributes()->getTaxScheme();
        if ($taxScheme) {
            $collectionItem->setData(
                'extension_attributes',
                $taxRule->getExtensionAttributes()
            );
            $collectionItem->setTaxSchemeId($taxScheme->getSchemeId());
        }
        return $collectionItem;
    }
}
