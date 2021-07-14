<?php
namespace Gw\AutoCustomerGroup\Plugin;

use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

class CopyTaxDetailsPlugin
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
        foreach ($appliedTaxes as $code => $appliedTax) {
            $taxableAmount = $appliedTax->getTaxableAmount();
            $taxableBaseAmount = $baseAppliedTaxes[$code]->getTaxableAmount();
            foreach ($result as $index => $res) {
                if ($res['id'] == $code) {
                    $result[$index]['extension_attributes']['taxable_amount'] = $taxableAmount;
                    $result[$index]['extension_attributes']['base_taxable_amount'] = $taxableBaseAmount;
                }
            }
        }
        return $result;
    }
}
