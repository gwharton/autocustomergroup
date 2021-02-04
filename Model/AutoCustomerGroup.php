<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;

class AutoCustomerGroup
{
    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        TaxSchemes $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * @param string $countryCode
     * @param string $taxId
     * @return DataObject|null
     */
    public function checkTaxId(
        $countryCode,
        $taxId
    ) {
        foreach ($this->taxSchemes->getEnabledTaxSchemes() as $taxScheme) {
            if ($taxScheme->isSchemeCountry($countryCode)) {
                return $taxScheme->checkTaxId($countryCode, $taxId);
            }
        }
        return null;
    }

    /**
     * @param string $customerCountryCode
     * @param string $customerPostCode
     * @param DataObject $taxIdValidationResults
     * @param Quote $quote
     * @param int $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $taxIdValidationResults,
        $quote,
        $storeId
    ) {
        foreach ($this->taxSchemes->getEnabledTaxSchemes() as $taxScheme) {
            if ($taxScheme->isSchemeCountry($customerCountryCode)) {
                return $taxScheme->getCustomerGroup(
                    $customerCountryCode,
                    $customerPostCode,
                    $taxIdValidationResults,
                    $quote,
                    $storeId
                );
            }
        }
        return null;
    }
}
