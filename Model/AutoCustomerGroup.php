<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\Validator\AbstractValidator;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;

class AutoCustomerGroup
{
    /**
     * @var AbstractValidator[]
     */
    private $validators;

    public function __construct(
        array $validators = []
    ) {
        $this->validators = $validators;
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
        foreach ($this->validators as $validator) {
            if ($validator->isEnabled() && $validator->checkCountry($countryCode)) {
                return $validator->checkTaxId($countryCode, $taxId);
            }
        }
        return new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Tax Identifier Validator is not enabled for this country.'),
        ]);
    }

    /**
     * @param string $customerCountryCode
     * @param DataObject $taxIdValidationResults
     * @param Quote $quote
     * @param int $storeId
     * @return void
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $taxIdValidationResults,
        $quote,
        $storeId
    ) {
        foreach ($this->validators as $validator) {
            if ($validator->isEnabled() && $validator->checkCountry($customerCountryCode)) {
                return $validator->getCustomerGroup(
                    $customerCountryCode,
                    $taxIdValidationResults,
                    $quote,
                    $storeId
                );
            }
        }
        return null;
    }
}
