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

    const XML_PATH_EU_COUNTRIES_LIST = 'general/country/eu_countries';
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
     */
    public function getCustomerGroup($customerCountryCode, $vatValidationResult, $quote, $store = null)
    {
        $merchantCountry = $this->helper->getMerchantCountryCode($store);

        //If the store is not based in the EU but the item is being shipped to the EU and the order value ex vat
        //is above the threshold then place in the importabovethreshold group
        $importThreshold = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/importthreshold",
            ScopeInterface::SCOPE_STORE,
            $store
        );
        if (!$this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            ($this->getOrderTotal($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importabovethreshold",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //If the store is based in the EU and the item is being shipped to the EU, but they are not the same country
        //and the VAT Id is valid then place in validintraeu group
        if ($this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            $merchantCountry != $customerCountryCode &&
            $vatValidationResult->getRequestSuccess() &&
            $vatValidationResult->getIsValid()) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/validintraeu",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //If the store is not based in the EU and the item is being shipped to the EU and the VAT Id is valid
        //then place in validimport group
        if (!$this->isCountryInEU($merchantCountry) &&
            $this->isCountryInEU($customerCountryCode) &&
            $vatValidationResult->getRequestSuccess() &&
            $vatValidationResult->getIsValid()) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/validimport",
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
     * Check whether specified country is in EU countries list
     *
     * @param string $countryCode
     * @param null|int $storeId
     * @return bool
     */
    private function isCountryInEU($countryCode, $storeId = null)
    {
        $euCountries = explode(
            ',',
            $this->scopeConfig->getValue(self::XML_PATH_EU_COUNTRIES_LIST, ScopeInterface::SCOPE_STORE, $storeId)
        );
        return in_array($countryCode, $euCountries);
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
