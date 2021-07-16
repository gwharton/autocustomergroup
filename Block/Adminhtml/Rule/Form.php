<?php
namespace Gw\AutoCustomerGroup\Block\Adminhtml\Rule;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Magento\Tax\Model\Rate\Source;
use Magento\Tax\Model\TaxClass\Source\Customer;
use Magento\Tax\Model\TaxClass\Source\Product;

class Form extends \Magento\Tax\Block\Adminhtml\Rule\Edit\Form
{
    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Source $rateSource
     * @param TaxRuleRepositoryInterface $ruleService
     * @param TaxClassRepositoryInterface $taxClassService
     * @param Customer $customerTaxClassSource
     * @param Product $productTaxClassSource
     * @param TaxSchemes $taxSchemes
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Source $rateSource,
        TaxRuleRepositoryInterface $ruleService,
        TaxClassRepositoryInterface $taxClassService,
        Customer $customerTaxClassSource,
        Product $productTaxClassSource,
        TaxSchemes $taxSchemes,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $rateSource,
            $ruleService,
            $taxClassService,
            $customerTaxClassSource,
            $productTaxClassSource,
            $data
        );
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * Prepare form before rendering HTML.
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    protected function _prepareForm(): Form
    {
        parent::_prepareForm();

        $form = $this->getForm();
        $fieldset = $form->getElement('base_fieldset');
        $taxSchemes = $this->taxSchemes->toOptionArray();
        array_unshift($taxSchemes, ['value' => null, 'label' => 'Not Linked']);

        $fieldset->addField(
            'tax_scheme_id',
            'select',
            ['name' => 'tax_scheme_id', 'label' => __('Tax Scheme'), 'required' => false, 'values' => $taxSchemes]
        );

        $taxRuleId = $this->_coreRegistry->registry('tax_rule_id');
        $taxRule = $this->ruleService->get($taxRuleId);

        $sessionFormValues = (array)$this->_coreRegistry->registry('tax_rule_form_data');
        $taxRuleData = isset($taxRule) ? $this->extractTaxRuleData($taxRule) : [];
        $formValues = array_merge($taxRuleData, $sessionFormValues);
        $formValues['tax_calculation_rule_id'] = $taxRuleId;
        $form->setValues($formValues);
        return $this;
    }

    protected function extractTaxRuleData($taxRule)
    {
        $taxRuleData = parent::extractTaxRuleData($taxRule);
        $taxScheme = $taxRule->getExtensionAttributes()->getTaxScheme();
        if ($taxScheme) {
            $taxRuleData['tax_scheme_id'] = $taxScheme->getSchemeId();
        }
        return $taxRuleData;
    }
}
