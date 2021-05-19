<?php
namespace Gw\AutoCustomerGroup\Block\Adminhtml\Rate;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Controller\RegistryConstants;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Form extends \Magento\Tax\Block\Adminhtml\Rate\Form
{
    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Data\FormFactory $formFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\Config\Source\Country $country
     * @param \Magento\Tax\Block\Adminhtml\Rate\Title\FieldsetFactory $fieldsetFactory
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\Tax\Api\TaxRateRepositoryInterface $taxRateRepository
     * @param \Magento\Tax\Model\TaxRateCollection $taxRateCollection
     * @param \Magento\Tax\Model\Calculation\Rate\Converter $taxRateConverter
     * @param TaxSchemes $taxSchemes
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Data\FormFactory $formFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\Config\Source\Country $country,
        \Magento\Tax\Block\Adminhtml\Rate\Title\FieldsetFactory $fieldsetFactory,
        \Magento\Tax\Helper\Data $taxData,
        \Magento\Tax\Api\TaxRateRepositoryInterface $taxRateRepository,
        \Magento\Tax\Model\TaxRateCollection $taxRateCollection,
        \Magento\Tax\Model\Calculation\Rate\Converter $taxRateConverter,
        TaxSchemes $taxSchemes,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $formFactory,
            $regionFactory,
            $country,
            $fieldsetFactory,
            $taxData,
            $taxRateRepository,
            $taxRateCollection,
            $taxRateConverter,
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
    protected function _prepareForm()
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

        $taxRateId = $this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_ID);

        try {
            if ($taxRateId) {
                $taxRateDataObject = $this->_taxRateRepository->get($taxRateId);
            }
        // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (NoSuchEntityException $e) {
            //tax rate not found//
        }

        $sessionFormValues = (array)$this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_FORM_DATA);
        $formData = isset($taxRateDataObject)
            ? $this->_taxRateConverter->createArrayFromServiceObject($taxRateDataObject)
            : [];
        $formData = array_merge($formData, $sessionFormValues);
        $form->setValues($formData);
        return $this;
    }
}
