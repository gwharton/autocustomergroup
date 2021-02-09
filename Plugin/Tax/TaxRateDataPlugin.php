<?php

namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Model\Calculation\Rate\Converter;

class TaxRateDataPlugin
{
    /**
     * Add the Tax Scheme Form data to Tax Rate Object
     *
     * @param Converter $subject
     * @param TaxRateInterface $result
     * @param $formData
     * @return TaxRateInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterPopulateTaxRateData(
        Converter $subject,
        $result,
        $formData
    ) {
        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setTaxSchemeId($this->extractFormData($formData, 'tax_scheme_id'));
        $result->setExtensionAttributes($extensionAttributes);
        return $result;
    }

    /**
     * Populate Form data with Tax Scheme Id
     *
     * @param Converter $subject
     * @param array $result
     * @param TaxRateInterface $taxRate
     * @param boolean $returnNumericLogic
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCreateArrayFromServiceObject(
        Converter $subject,
        $result,
        TaxRateInterface $taxRate,
        $returnNumericLogic = false
    ) {
        $result['tax_scheme_id'] = $taxRate->getExtensionAttributes()->getTaxSchemeId();
        return $result;
    }

    /**
     * Determines if an array value is set in the form data array and returns it.
     *
     * @param array $formData the form to get data from
     * @param string $fieldName the key
     * @return null|string
     */
    private function extractFormData($formData, $fieldName)
    {
        if (isset($formData[$fieldName])) {
            return $formData[$fieldName];
        }
        return null;
    }
}
