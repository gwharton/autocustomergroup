<?php
namespace Gw\AutoCustomerGroup\Model\Calculation;

use Magento\Tax\Api\Data\AppliedTaxInterface;

class TaxRuleExtractor
{
    /**
     * Extract the Tax Rule ID from the applied taxes, and ensure it is
     * set in the extension attributes of the applied Rates
     *
     * @param AppliedTaxInterface $appliedTax
     * @param array $originalRate
     * @return AppliedTaxInterface
     */
    public function extractSingle(
        AppliedTaxInterface $appliedTax,
        array $originalRate
    ): AppliedTaxInterface {
        $rates = $appliedTax->getRates();
        foreach ($originalRate['rates'] as $rate) {
            $rule_id = $rate['rule_id'];
            if ($rule_id) {
                $code = $rate['code'];
                $extensionAtt = $rates[$code]->getExtensionAttributes();
                $taxrulesarray = $extensionAtt->getTaxRuleIds() ?: [];
                if (!in_array($rule_id, $taxrulesarray)) {
                    $taxrulesarray[] = $rule_id;
                }
                $extensionAtt->setTaxRuleIds($taxrulesarray);
                $rates[$code]->setExtensionAttributes($extensionAtt);
            }
        }
        $appliedTax->setRates($rates);
        return $appliedTax;
    }

    /**
     * @param AppliedTaxInterface[] $appliedTaxes
     * @param array $originalRates
     * @return array
     */
    public function extractMultiple(array $appliedTaxes, array $originalRates): array
    {
        foreach ($originalRates as $appliedRate) {
            foreach ($appliedRate['rates'] as $rate) {
                $rule_id = $rate['rule_id'];
                if ($rule_id) {
                    $code = $rate['code'];
                    $outputRates = $appliedTaxes[$code]->getRates();
                    $extensionAtt = $outputRates[$code]->getExtensionAttributes();
                    $taxrulesarray = $extensionAtt->getTaxRuleIds() ?: [];
                    if (!in_array($rule_id, $taxrulesarray)) {
                        $taxrulesarray[] = $rule_id;
                    }
                    $extensionAtt->setTaxRuleIds($taxrulesarray);
                    $outputRates[$code]->setExtensionAttributes($extensionAtt);
                    $appliedTaxes[$code]->setRates($outputRates);
                }
            }
        }
        return $appliedTaxes;
    }
}
