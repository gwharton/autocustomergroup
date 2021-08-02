<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Exception;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use SoapClient;

/**
 * Australian Test Numbers on Live system (No Sandbox)
 * https://abr.business.gov.au/Documentation/WebServiceResponse
 * 50 110 219 460 - EXAMPLE PTY LTD without GST
 * 72 629 951 766 - EXAMPLE HOUSE PTY LTD with GST
 */
class AustraliaGst extends AbstractTaxScheme
{
    const CODE = "australiagst";
    const SCHEME_CURRENCY = 'AUD';
    protected $code = self::CODE;
    const ABN_VALIDATION_WSDL_URL = 'https://abr.business.gov.au/abrxmlsearch/ABRXMLSearch.asmx?WSDL';

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = ['AU'];

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
                "Gw/AutoCustomerGroup/Model/TaxSchemes/AustraliaGst::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }
        $importThreshold = $this->getThresholdInBaseCurrency($storeId);
        //Merchant Country is in Australia
        //Item shipped to Australia
        //Therefore Domestic
        if ($this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Australia
        //Item shipped to Australia
        //GST Validated ABN Number Supplied
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
        //Merchant Country is not in Australia
        //Item shipped to Australia
        //Order Value equal or below threshold
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
        //Merchant Country is not in Australia
        //Item shipped to Australia
        //Order value below threshold
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
     * Peform validation of the ABN, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string|null $taxId
     * @return GatewayResponseInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface {
        $gatewayResponse = $this->gwrFactory->create();
        $gatewayResponse->setRequestMessage(__('Error during ABN verification.'));

        $sanitisedAbn = str_replace([' ', '-'], ['', ''], $taxId);

        if (!preg_match("/^[0-9]{11}$/", $sanitisedAbn) ||
            !$this->isSchemeCountry($countryCode) ||
            !$this->isValidAbn($sanitisedAbn)) {
            $gatewayResponse->setRequestMessage(__('Please enter a valid ABN number.'));
            return $gatewayResponse;
        }

        $apiguid = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/apiguid",
            ScopeInterface::SCOPE_STORE
        );

        if (extension_loaded('soap') && $apiguid) {
            try {
                $soapClient = new SoapClient(self::ABN_VALIDATION_WSDL_URL);

                $requestParams = [];
                $requestParams['searchString'] = $sanitisedAbn;
                $requestParams['includeHistoricalDetails'] ='N';
                $requestParams['authenticationGuid'] = $apiguid;

                $result = $soapClient->ABRSearchByABN($requestParams);

                $isCurrent = $result->ABRPayloadSearchResults->response->businessEntity->ABN->isCurrentIndicator;
                $gstValid = false;

                if (isset($result->ABRPayloadSearchResults->response->businessEntity->goodsAndServicesTax)) {
                    $gstValid = true;
                    $gstFrom = $result->ABRPayloadSearchResults->response
                        ->businessEntity->goodsAndServicesTax->effectiveFrom;
                    $day = $this->datetime->gmtDate("Y-m-d");
                    if ($gstFrom > $day) {
                        $gstValid = false;
                    } else {
                        $gstTo = $result->ABRPayloadSearchResults->response
                            ->businessEntity->goodsAndServicesTax->effectiveTo;
                        if (($gstTo != "0001-01-01") &&
                            ($gstTo < $day)) {
                            $gstValid = false;
                        }
                    }
                }
                $identifier = $result->ABRPayloadSearchResults->response->businessEntity->ABN->identifierValue;
                $datetime = $result->ABRPayloadSearchResults->response->dateTimeRetrieved;

                $gatewayResponse->setRequestSuccess(true);
                if (preg_match("/^[Yy](es)?$/", $isCurrent) &&
                    $gstValid &&
                    !empty($datetime)) {
                    $gatewayResponse->setRequestIdentifier($identifier);
                    $gatewayResponse->setRequestDate($datetime);
                    $gatewayResponse->setIsValid(true);
                    $gatewayResponse->setRequestMessage(
                        __('ABN validated and business is registered for GST with ATO.')
                    );
                } else {
                    $gatewayResponse->setIsValid(false);
                    $gatewayResponse->setRequestDate('');
                    $gatewayResponse->setRequestMessage(__('Please enter a valid ABN number, where ' .
                        'the business is registered for GST.'));
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
     * Validate an Australian Business Number (ABN)
     *
     * @param string|null $abn
     * @return bool
     */
    public function isValidAbn(?string $abn): bool
    {
        $weights = [10, 1, 3, 5, 7, 9, 11, 13, 15, 17, 19];

        // Strip non-numbers from the acn
        $abn = preg_replace('/[^0-9]/', '', $abn);

        // Check abn is 11 chars long
        if (strlen($abn) != 11) {
            return false;
        }

        // Subtract one from first digit
        $abn[0] = ((int)$abn[0] - 1);

        // Sum the products
        $sum = 0;
        foreach (str_split($abn) as $key => $digit) {
            $sum += ((int) $digit * $weights[$key]);
        }

        if (($sum % 89) != 0) {
            return false;
        }
        return true;
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return "Australia GST for Non Residents Scheme";
    }
}
