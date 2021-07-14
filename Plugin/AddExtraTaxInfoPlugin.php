<?php
namespace Gw\AutoCustomerGroup\Plugin;

use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Model\Calculation\AbstractCalculator;
use Magento\Tax\Model\TaxDetails\ItemDetail;

class AddExtraTaxInfoPlugin
{
    /**
     * @param AbstractCalculator $subject
     * @param ItemDetail $result
     * @param QuoteDetailsItemInterface $item
     * @param int $quantity
     * @param bool $round
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    public function afterCalculate(
        AbstractCalculator $subject,
        $result,
        QuoteDetailsItemInterface $item,
        $quantity,
        $round = true
    ) {
        $appliedTaxes = $result->getAppliedTaxes();
        foreach ($appliedTaxes as $index => $appliedTax) {
            $appliedTaxes[$index]['taxable_amount'] = $result->getRowTotal();
            $result->setAppliedTaxes($appliedTaxes);
        }
        return $result;
    }
}
