<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class NorwayVoec extends AbstractTaxScheme
{
    const CODE = "norwayvoec";
    protected $code = self::CODE;

    /**
     * Check if this Tax Scheme handles the requtested country
     *
     * @param string $country
     * @return bool
     */
    public function checkCountry($country)
    {
        return $this->isCountryNorway($country);
    }

    private function isCountryNorway($country)
    {
        return in_array($country, ['NO']);
    }

    /**
     * Get customer group based on Validation Result and Country of customer
     * @param string $customerCountryCode
     * @param string $customerPostCode
     * @param DataObject $vatValidationResult
     * @param Quote $quote
     * @param $store
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $vatValidationResult,
        $quote,
        $store = null
    ) {
        $merchantCountry = $this->helper->getMerchantCountryCode();
        $importThreshold = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $store
        );
        //Merchant Country is in Norway
        //Item shipped to Norway
        //Therefore Domestic
        if ($this->isCountryNorway($merchantCountry) &&
            $this->isCountryNorway($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Norway Business Number Supplied
        //Therefore Import B2B
        if (!$this->isCountryNorway($merchantCountry) &&
            $this->isCountryNorway($customerCountryCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //All items equal or below threshold
        //Therefore Import Taxed
        if (!$this->isCountryNorway($merchantCountry) &&
            $this->isCountryNorway($customerCountryCode) &&
            ($this->getMostExpensiveItem($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Any item is above threshold
        //Therefore Import Unaxed
        if (!$this->isCountryNorway($merchantCountry) &&
            $this->isCountryNorway($customerCountryCode) &&
            ($this->getMostExpensiveItem($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        return null;
    }

    /**
     * Peform validation of the VAT number, returning a gatewayResponse object
     *
     * https://www.skatteetaten.no/globalassets/bedrift-og-organisasjon/voec/how-to-treat-b2b-sales.pdf
     * States that you do not need to actually check the VAT number. If the buyer provides a Business
     * Number of the right format, then we should trust them.
     *
     * ": The Guidelines for the VOEC scheme, section 8: The customer shall be presumed to be a non-taxable
     * person. This presumption releases the interface from the burden of having to prove the status of the
     * customer."
     *
     * @param string $countryCode
     * @param string $businessNumber
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkTaxId($countryCode, $businessNumber)
    {
        $gatewayResponse = new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Error during VAT Number verification.'),
        ]);

        if (preg_match("/^[89][0-9]{8}$/", $businessNumber) &&
            $this->isCountryNorway($countryCode)) {
            $gatewayResponse->setIsValid(true);
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setRequestMessage(__('Business Registration Number is the correct format.'));
        } else {
            $gatewayResponse->setIsValid(false);
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setRequestMessage(__('Business Registration Number is not the correct format.'));
        }
        return $gatewayResponse;
    }
}
