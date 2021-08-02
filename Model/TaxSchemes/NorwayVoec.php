<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class NorwayVoec extends AbstractTaxScheme
{
    const CODE = "norwayvoec";
    const SCHEME_CURRENCY = 'NOK';
    protected $code = self::CODE;

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = ['NO'];

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
                "Gw/AutoCustomerGroup/Model/TaxSchemes/MorwayVoec::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }
        $importThreshold = $this->getThresholdInBaseCurrency($storeId);
        //Merchant Country is in Norway
        //Item shipped to Norway
        //Therefore Domestic
        if ($this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Norway Business Number Supplied
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
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //All items equal or below threshold
        //Therefore Import Taxed
        if (!$this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            ($this->getMostExpensiveItemBaseCurrency($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in Norway
        //Item shipped to Norway
        //Any item is above threshold
        //Therefore Import Unaxed
        if (!$this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode) &&
            ($this->getMostExpensiveItemBaseCurrency($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $storeId
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
     * @param string|null $taxId
     * @return GatewayResponseInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface {
        $gatewayResponse = $this->gwrFactory->create();
        $gatewayResponse->setRequestMessage(__('Error during VAT Number verification.'));

        if (preg_match("/^[89][0-9]{8}$/", $taxId) &&
            $this->isSchemeCountry($countryCode)) {
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

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return "Norway VOEC Scheme";
    }
}
