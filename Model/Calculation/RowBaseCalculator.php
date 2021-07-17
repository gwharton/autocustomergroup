<?php
namespace Gw\AutoCustomerGroup\Model\Calculation;

use Magento\Framework\DataObject;
use Magento\Tax\Api\Data\AppliedTaxInterface;
use Magento\Tax\Api\Data\AppliedTaxInterfaceFactory;
use Magento\Tax\Api\Data\AppliedTaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsItemInterfaceFactory;
use Magento\Tax\Api\TaxClassManagementInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\Calculation\RowBaseCalculator as CalculationRowBaseCalculator;
use Magento\Tax\Model\Config;

/**
 * Add the necessary rule data to the Applied Tax Object so it can be used
 * later in the order saving process.
 */
class RowBaseCalculator extends CalculationRowBaseCalculator
{
    /**
     * @var TaxRuleExtractor
     */
    private $taxRuleExtractor;

    /**
     * @param TaxClassManagementInterface $taxClassService
     * @param TaxDetailsItemInterfaceFactory $taxDetailsItemFac
     * @param AppliedTaxInterfaceFactory $appliedTaxFactory
     * @param AppliedTaxRateInterfaceFactory $appliedTaxRateFac
     * @param Calculation $calculationTool
     * @param Config $config
     * @param int $storeId
     * @param TaxRuleExtractor $taxRuleExtractor
     * @param DataObject|null $addressRateRequest
     */
    public function __construct(
        TaxClassManagementInterface $taxClassService,
        TaxDetailsItemInterfaceFactory $taxDetailsItemFac,
        AppliedTaxInterfaceFactory $appliedTaxFactory,
        AppliedTaxRateInterfaceFactory $appliedTaxRateFac,
        Calculation $calculationTool,
        Config $config,
        int $storeId,
        TaxRuleExtractor $taxRuleExtractor,
        DataObject $addressRateRequest = null
    ) {
        parent::__construct(
            $taxClassService,
            $taxDetailsItemFac,
            $appliedTaxFactory,
            $appliedTaxRateFac,
            $calculationTool,
            $config,
            $storeId,
            $addressRateRequest
        );
        $this->taxRuleExtractor = $taxRuleExtractor;
    }

    /**
     * @param float $rowTax
     * @param array $appliedRate
     * @return AppliedTaxInterface
     */
    protected function getAppliedTax($rowTax, $appliedRate): AppliedTaxInterface
    {
        return $this->taxRuleExtractor->extractSingle(parent::getAppliedTax($rowTax, $appliedRate), $appliedRate);
    }

    /**
     * @param float $rowTax
     * @param float $totalTaxRate
     * @param array $appliedRates
     * @return AppliedTaxInterface[]
     */
    protected function getAppliedTaxes($rowTax, $totalTaxRate, $appliedRates): array
    {
        return $this->taxRuleExtractor->extractMultiple(parent::getAppliedTaxes($rowTax, $totalTaxRate, $appliedRates), $appliedRates);
    }
}
