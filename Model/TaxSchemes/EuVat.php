<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Exception;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use SoapClient;

/**
 * Sandbox Test Number
 * 100 Valid
 * 200 Invalid
 */
class EuVat extends AbstractTaxScheme
{
    const CODE = "euvat";
    const SCHEME_CURRENCY = 'EUR';
    protected $code = self::CODE;

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
     * @param string|null $customerPostCode
     * @param GatewayResponseInterface $vatValidationResult
     * @param Quote $quote
     * @param int|null $storeId
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        ?string $customerPostCode,
        GatewayResponseInterface $vatValidationResult,
        Quote $quote,
        ?int $storeId
    ): ?int {
        $merchantCountry = $this->getMerchantCountryCode($storeId);
        if (empty($merchantCountry)) {
            $this->logger->critical(
                "Gw/AutoCustomerGroup/Model/TaxSchemes/EuVat::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }
        $merchantPostCode = $this->getMerchantPostCode($storeId);
        if (empty($merchantPostCode) && $merchantCountry == "GB") {
            $this->logger->critical(
                "Gw/AutoCustomerGroup/Model/TaxSchemes/EuVat::getCustomerGroup() : " .
                "Merchant Postcode not set in UK (We need to determine if you are in NI)."
            );
            return null;
        }
        $importThreshold = $this->getThresholdInBaseCurrency($storeId);
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
     * @param string|null $taxId
     * @return GatewayResponseInterface
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface {
        $gatewayResponse = $this->gwrFactory->create();
        $gatewayResponse->setRequestMessage(__('Error during VAT Number verification.'));

        $requesterCountryCode = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationcountry",
            ScopeInterface::SCOPE_STORE
        );
        $requesterVatNumber = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE
        );
        $newVat = str_replace(
            [' ', '-', $this->getCountryCodeForVatNumber($countryCode)],
            ['', '', ''],
            $taxId
        );
        $newRequesterVat = str_replace(
            [' ', '-', $this->getCountryCodeForVatNumber($requesterCountryCode)],
            ['', '', ''],
            $requesterVatNumber
        );

        if (empty($newVat) ||
            empty($newRequesterVat) ||
            empty($this->getCountryCodeForVatNumber($countryCode)) ||
            empty($this->getCountryCodeForVatNumber($requesterCountryCode)) ||
            !$this->isSchemeCountry($countryCode)) {
            $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number.'));
            return $gatewayResponse;
        }

        if (extension_loaded('soap')) {
            try {
                $soapClient = new SoapClient($this->getWsdlUrl());

                $requestParams = [];
                $requestParams['countryCode'] = $this->getCountryCodeForVatNumber($countryCode);
                $requestParams['vatNumber'] = $newVat;
                $requestParams['requesterCountryCode'] = $this->getCountryCodeForVatNumber($requesterCountryCode);
                $requestParams['requesterVatNumber'] = $newRequesterVat;

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
            } catch (Exception $exception) {
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
    private function getCountryCodeForVatNumber(string $countryCode): string
    {
        // Greece uses a different code for VAT numbers then its country code
        // See: http://ec.europa.eu/taxation_customs/vies/faq.html#item_11
        // And https://en.wikipedia.org/wiki/VAT_identification_number:
        // "The full identifier starts with an ISO 3166-1 alpha-2 (2 letters) country code
        // (except for Greece, which uses the ISO 639-1 language code EL for the Greek language,
        // instead of its ISO 3166-1 alpha-2 country code GR)"

        return $countryCode === 'GR' ? 'EL' : $countryCode;
    }

    /**
     * Return the correct SOAP Url depending on the environment settiong
     *
     * @return string
     */
    private function getWsdlUrl(): string
    {
        if ($this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/environment",
            ScopeInterface::SCOPE_STORE
        ) == Environment::ENVIRONMENT_PRODUCTION) {
            return "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";
        } else {
            return "https://ec.europa.eu/taxation_customs/vies/checkVatTestService.wsdl";
        }
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return "EU VAT OSS Scheme";
    }
}
