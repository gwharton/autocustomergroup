<?php
namespace Gw\AutoCustomerGroup\Model\Validator;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 * Live EU VAT numbers
 * IE3206488LH - Stripe
 * IE8256796U - Microsoft
 * IE6388047V - Google
 */
class EuVat extends AbstractValidator
{
    const CODE = "euvat";
    protected $code = self::CODE;

    const VAT_VALIDATION_WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /**
     * Check if this Validator handles the requtested country
     *
     * @param string $country
     * @return bool
     */
    public function checkCountry($country)
    {
        return $this->isCountryInEU($country);
    }

    /**
     * Get customer group based on VAT Check Result and Country of customer
     * @param string $customerCountryCode
     * @param DataObject $vatValidationResult
     * @param Quote $quote
     * @param $store
     * @return int
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
        $merchantCountry = $this->helper->getMerchantCountryCode($store);
        $merchantPostCode = $this->helper->getMerchantPostCode($store);
        $importThreshold = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $store
        );
        //Merchant Country is in the EU
        //Item shipped to the EU
        //Both countries the same
        //Therefore Domestic Taxed
        if ($this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            $merchantCountry == $customerCountryCode) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestictaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the EU or NI
        //Item shipped to the EU
        //Both countries are not the same
        //VAT No is valid
        //Therefore Intra EU Zero
        if (($this->isCountryInEU($merchantCountry) || $this->isNi($merchantCountry, $merchantPostCode)) &&
            $this->isCountryInEU($customerCountryCode) &&
            $merchantCountry != $customerCountryCode &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeuzero",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the EU or NI
        //Item shipped to the EU
        //Both countries are not the same
        //VAT No is not valid
        //Therefore Intra EU Distance Sale Taxed
        if (($this->isCountryInEU($merchantCountry) || $this->isNi($merchantCountry, $merchantPostCode)) &&
            $this->isCountryInEU($customerCountryCode) &&
            $merchantCountry != $customerCountryCode &&
            !$this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeudistancesaletaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //VAT No is valid.
        //Therefore Import Zero
        if (!$this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importzero",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //Order value is equal or below threshold
        //Therefore Import Taxed
        if (!$this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            ($this->getOrderTotal($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //Order value is above threshold
        //Therefore Import Unaxed
        if (!$this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            ($this->getOrderTotal($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        return null;
    }

    /**
     * Peform validation of the VAT Number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @param string $requesterCountryCode
     * @param string $requesterVatNumber
     * @return DataObject
     */
    public function checkTaxId($countryCode, $vatNumber)
    {
        $gatewayResponse = new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Error during VAT Number verification.'),
        ]);

        if (!extension_loaded('soap')) {
            return $gatewayResponse;
        }

        $requesterCountryCode = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationcountry",
            ScopeInterface::SCOPE_STORE
        );
        $requesterVatNumber = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE
        );

        if (!$this->canCheckVatNumber($countryCode, $vatNumber, $requesterCountryCode, $requesterVatNumber)) {
            return $gatewayResponse;
        }

        $countryCodeForVatNumber = $this->getCountryCodeForVatNumber($countryCode);
        $requesterCountryCodeForVatNumber = $this->getCountryCodeForVatNumber($requesterCountryCode);

        try {
            $soapClient = $this->createVatNumberValidationSoapClient();

            $requestParams = [];
            $requestParams['countryCode'] = $countryCodeForVatNumber;
            $requestParams['vatNumber'] =
                str_replace([' ', '-', $countryCodeForVatNumber], ['', '', ''], $vatNumber);
            $requestParams['requesterCountryCode'] = $requesterCountryCodeForVatNumber;
            $requestParams['requesterVatNumber'] =
                str_replace([' ', '-', $requesterCountryCodeForVatNumber], ['', '', ''], $requesterVatNumber);

            $result = $soapClient->checkVatApprox($requestParams);

            $gatewayResponse->setIsValid((bool)$result->valid);
            $gatewayResponse->setRequestDate((string)$result->requestDate);
            $gatewayResponse->setRequestIdentifier((string)$result->requestIdentifier);
            $gatewayResponse->setRequestSuccess(true);

            if ($gatewayResponse->getIsValid()) {
                $gatewayResponse->setRequestMessage(__('VAT Number validated with VIES.'));
            } else {
                $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number including country code.'));
            }
        } catch (\Exception $exception) {
            $gatewayResponse->setIsValid(false);
            $gatewayResponse->setRequestDate('');
            $gatewayResponse->setRequestIdentifier('');
        }
        return $gatewayResponse;
    }

    /**
     * Create SOAP client based on VAT validation service WSDL
     *
     * @param boolean $trace
     * @return \SoapClient
     */
    private function createVatNumberValidationSoapClient($trace = false)
    {
        return new \SoapClient(self::VAT_VALIDATION_WSDL_URL, ['trace' => $trace]);
    }

    /**
     * Check if parameters are valid to send to VAT validation service
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @param string $requesterCountryCode
     * @param string $requesterVatNumber
     * @return boolean
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function canCheckVatNumber(
        $countryCode,
        $vatNumber,
        $requesterCountryCode,
        $requesterVatNumber
    ) {
        return !(!is_string($countryCode)
            || !is_string($vatNumber)
            || !is_string($requesterCountryCode)
            || !is_string($requesterVatNumber)
            || empty($countryCode)
            || !$this->isCountryInEU($countryCode)
            || empty($vatNumber)
            || empty($requesterCountryCode) && !empty($requesterVatNumber)
            || !empty($requesterCountryCode) && empty($requesterVatNumber)
            || !empty($requesterCountryCode) && !$this->isCountryInEU($requesterCountryCode)
        );
    }

    /**
     * Returns the country code to use in the VAT number which is not always the same as the normal country code
     *
     * @param string $countryCode
     * @return string
     */
    private function getCountryCodeForVatNumber(string $countryCode)
    {
        // Greece uses a different code for VAT numbers then its country code
        // See: http://ec.europa.eu/taxation_customs/vies/faq.html#item_11
        // And https://en.wikipedia.org/wiki/VAT_identification_number:
        // "The full identifier starts with an ISO 3166-1 alpha-2 (2 letters) country code
        // (except for Greece, which uses the ISO 639-1 language code EL for the Greek language,
        // instead of its ISO 3166-1 alpha-2 country code GR)"

        return $countryCode === 'GR' ? 'EL' : $countryCode;
    }
}
