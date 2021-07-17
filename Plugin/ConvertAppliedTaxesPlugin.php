<?php
namespace Gw\AutoCustomerGroup\Plugin;

use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

/**
 * Ensure that the Extension Attributes for Tax Rule Ids that were set are copied over to the
 * applied taxes object that the order will use.
 */
class ConvertAppliedTaxesPlugin
{
    /**
     * @param CommonTaxCollector $subject
     * @param $result
     * @param AppliedTaxInterface[] $appliedTaxes
     * @param AppliedTaxInterface[] $baseAppliedTaxes
     * @param array $extraInfo
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterConvertAppliedTaxes(
        CommonTaxCollector $subject,
        $result,
        $appliedTaxes,
        $baseAppliedTaxes,
        $extraInfo = []
    ) {
        foreach ($result as $index1 => $appliedTax) {
            foreach ($appliedTax['rates'] as $index2 => $rate) {
                $extAtt = $appliedTaxes[$appliedTax['id']]->getRates()[$rate['code']]->getExtensionAttributes();
                $result[$index1]['rates'][$index2]['extension_attributes']['tax_rule_ids'] = $extAtt->getTaxRuleIds() ?? [];
            }
        }
        return $result;
    }
}
