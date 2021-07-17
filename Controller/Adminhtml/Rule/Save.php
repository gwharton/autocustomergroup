<?php
namespace Gw\AutoCustomerGroup\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

/**
 * When saving Tax Rules, ensure that if a tax scheme is linked, that
 * it is saved into the extension attributes for the TaxRule.
 */
class Save extends \Magento\Tax\Controller\Adminhtml\Rule\Save
{
    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param Context $context
     * @param Registry $coreRegistry
     * @param TaxRuleRepositoryInterface $ruleService
     * @param TaxRuleInterfaceFactory $taxRuleDataObjectFactory
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        TaxRuleRepositoryInterface $ruleService,
        TaxRuleInterfaceFactory $taxRuleDataObjectFactory,
        TaxSchemes $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
        parent::__construct(
            $context,
            $coreRegistry,
            $ruleService,
            $taxRuleDataObjectFactory
        );
    }

    protected function populateTaxRule($postData): TaxRuleInterface
    {
        $taxRule = parent::populateTaxRule($postData);
        if (isset($postData['tax_scheme_id']) && $postData['tax_scheme_id'] != "") {
            $ea = $taxRule->getExtensionAttributes();
            $ea->setTaxScheme($this->taxSchemes->getTaxScheme($postData['tax_scheme_id']));
            $taxRule->setExtensionAttributes($ea);
        }
        return $taxRule;
    }
}
