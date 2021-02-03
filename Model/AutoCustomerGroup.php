<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes\AbstractTaxScheme;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;

class AutoCustomerGroup
{
    /**
     * @var AbstractTaxScheme[]
     */
    private $taxSchemes;

    public function __construct(
        array $taxSchemes = []
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * @param string $countryCode
     * @param string $taxId
     * @return DataObject
     */
    public function checkTaxId(
        $countryCode,
        $taxId
    ) {
        foreach ($this->taxSchemes as $taxScheme) {
            if ($taxScheme->isEnabled() && $taxScheme->checkCountry($countryCode)) {
                return $taxScheme->checkTaxId($countryCode, $taxId);
            }
        }
        return new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Tax Scheme is not enabled for this country.'),
        ]);
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
        foreach ($this->taxSchemes as $taxScheme) {
            if ($taxScheme->isEnabled() && $taxScheme->checkCountry($customerCountryCode)) {
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
