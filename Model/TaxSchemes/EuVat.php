<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use SoapClient;

/**
 * Live EU VAT numbers
 * IE3206488LH - Stripe
 * IE8256796U - Microsoft
 * IE6388047V - Google
 */
class EuVat extends AbstractTaxScheme
{
    const CODE = "euvat";
    const SCHEME_CURRENCY = 'EUR';
    protected $code = self::CODE;

    const VAT_VALIDATION_WSDL_URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = [  'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
                                    'IT','LV','LT','LU','MT','MC','NL','PL','PT','RO','SK','SI','ES','SE'];

    /**
     * Get customer group based on Validation Result and Country of customer
     * @param string $customerCountryCode
     * @param DataObject $vatValidationResult
     * @param Quote $quote
     * @param int|null $storeId
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $vatValidationResult,
        $quote,
        $storeId
    ) {
        $merchantCountry = $this->getMerchantCountryCode($storeId);
        $merchantPostCode = $this->getMerchantPostCode($storeId);
        $importThreshold = $this->getThresholdInBaseCurrency($this->getWebsiteIdFromStoreId($storeId));
        //Merchant Country is in the EU
        //Item shipped to the EU
        //Both countries the same
        //Therefore Domestic
        if ($this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            $merchantCountry == $customerCountryCode) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is in the EU or NI
        //Item shipped to the EU
        //Both countries are not the same
        //Validated EU VAT Number Supplied
        //Therefore Intra EU B2B
        if (($this->isSchemeCountry($merchantCountry) ||
            $this->isNi($merchantCountry, $merchantPostCode)) &&
            $this->isSchemeCountry($customerCountryCode) &&
            $merchantCountry != $customerCountryCode &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is in the EU or NI
        //Item shipped to the EU
        //Both countries are not the same
        //Validated EU VAT Number Not Supplied
        //Therefore Intra EU B2C
        if (($this->isSchemeCountry($merchantCountry) ||
            $this->isNi($merchantCountry, $merchantPostCode)) &&
            $this->isSchemeCountry($customerCountryCode) &&
            $merchantCountry != $customerCountryCode &&
            !$this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2c",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //Validated EU VAT Number Supplied
        //Therefore Import B2B
        if (!$this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //Order value is equal or below threshold
        //Therefore Import Taxed
        if (!$this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            ($this->getOrderTotalBaseCurrency($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in the EU
        //Item shipped to the EU
        //Order value is above threshold
        //Therefore Import Unaxed
        if (!$this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            ($this->getOrderTotalBaseCurrency($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return null;
    }

    /**
     * Peform validation of the VAT Number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string $vatNumber
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

        $requesterCountryCode = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationcountry",
            ScopeInterface::SCOPE_STORE
        );
        $requesterVatNumber = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE
        );
        $countryCodeForVatNumber = $this->getCountryCodeForVatNumber($countryCode);
        $requesterCountryCodeForVatNumber = $this->getCountryCodeForVatNumber($requesterCountryCode);
        $sanitisedVAT = str_replace(
            [' ', '-', $countryCodeForVatNumber],
            ['', '', ''],
            $vatNumber
        );
        $sanitisedRequesterVAT = str_replace(
            [' ', '-',
            $requesterCountryCodeForVatNumber],
            ['', '', ''],
            $requesterVatNumber
        );

        if (empty($sanitisedVAT) ||
            empty($sanitisedRequesterVAT) ||
            empty($countryCodeForVatNumber) ||
            empty($requesterCountryCodeForVatNumber) ||
            !$this->isSchemeCountry($countryCode)) {
            $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number.'));
            return $gatewayResponse;
        }

        if (extension_loaded('soap')) {
            try {
                $soapClient = new SoapClient(self::VAT_VALIDATION_WSDL_URL);

                $requestParams = [];
                $requestParams['countryCode'] = $countryCodeForVatNumber;
                $requestParams['vatNumber'] = $sanitisedVAT;
                $requestParams['requesterCountryCode'] = $requesterCountryCodeForVatNumber;
                $requestParams['requesterVatNumber'] = $sanitisedRequesterVAT;

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
                $gatewayResponse->setRequestSuccess(false);
                $gatewayResponse->setIsValid(false);
                $gatewayResponse->setRequestDate('');
                $gatewayResponse->setRequestIdentifier('');
            }
        }
        return $gatewayResponse;
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
