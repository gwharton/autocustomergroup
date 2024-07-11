<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\BadResponseException;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterfaceFactory;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Real EU VAT numbers (as of 11/07/2024)
 * NL - 810433941B01 - COOLBLUE B.V. - VALID
 * IE - IE8256796U - MICROSOFT IRELAND OPERATIONS LIMITED - VALID
 * IE - IE3206488LH - STRIPE PAYMENTS EUROPE LIMITED - VALID
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
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ClientFactory $clientFactory
     * @param Json $serializer
     * @param DateTime $datetime
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     * @param GatewayResponseInterfaceFactory $gwrFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ClientFactory $clientFactory,
        Json $serializer,
        DateTime $datetime,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory,
        GatewayResponseInterfaceFactory $gwrFactory
    ) {
        parent::__construct(
            $scopeConfig,
            $logger,
            $storeManager,
            $datetime,
            $currencyFactory,
            $gwrFactory
        );
        $this->clientFactory = $clientFactory;
        $this->serializer = $serializer;
    }

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

        $sanitisedVatNumber = str_replace(
            [' ', '-', $this->getCountryCodeForVatNumber($countryCode)],
            ['', '', ''],
            $taxId
        );

        if (empty($sanitisedVatNumber) ||
            empty($this->getCountryCodeForVatNumber($countryCode)) ||
            !$this->isSchemeCountry($countryCode)) {
            $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number.'));
            return $gatewayResponse;
        }

        try {
            $body['countryCode'] = $countryCode;
            $body['vatNumber'] = $sanitisedVatNumber;

            $requesterCountryCode = $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/registrationcountry",
                ScopeInterface::SCOPE_STORE
            );
            $requesterVatNumber = $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/viesregistrationnumber",
                ScopeInterface::SCOPE_STORE
            );

            if ($requesterCountryCode && $requesterVatNumber) {
                $sanitisedRequesterVatNumber = str_replace(
                    [' ', '-', $this->getCountryCodeForVatNumber($requesterCountryCode)],
                    ['', '', ''],
                    $requesterVatNumber
                );
                $body['requesterMemberStateCode'] = $requesterCountryCode;
                $body['requesterNumber'] = $sanitisedRequesterVatNumber;
            }

            $client = $this->clientFactory->create();
            $response = $client->send(
                new Request(
                    "POST",
                    $this->getBaseUrl() . "/check-vat-number",
                    [
                        'Content-Type' => "application/json",
                        'Accept' => "application/json"
                    ],
                    $this->serializer->serialize($body)
                )
            );
            $responseBody = $response->getBody();
            $vatRegistration = $this->serializer->unserialize($responseBody->getContents());
            if (isset($vatRegistration['actionSucceeded']) && $vatRegistration['actionSucceeded'] == false) {
                $gatewayResponse->setIsValid(false);
                $gatewayResponse->setRequestSuccess(false);
                $gatewayResponse->setRequestMessage(__('There was an error checking the VAT number.'));
            } else {
                $gatewayResponse->setIsValid($vatRegistration['valid']);
                $gatewayResponse->setRequestSuccess(true);
                $gatewayResponse->setRequestDate($vatRegistration['requestDate']);
                $gatewayResponse->setRequestIdentifier($vatRegistration['requestIdentifier']);

                if ($gatewayResponse->getIsValid()) {
                    $gatewayResponse->setRequestMessage(__('VAT Number validated with VIES.'));
                } else {
                    $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number including country code.'));
                }
            }
        } catch (BadResponseException $e) {
            switch ($e->getCode()) {
                case 404:
                    $gatewayResponse->setIsValid(false);
                    $gatewayResponse->setRequestSuccess(true);
                    $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number.'));
                    break;
                default:
                    $gatewayResponse->setIsValid(false);
                    $gatewayResponse->setRequestSuccess(false);
                    $gatewayResponse->setRequestMessage(__('There was an error checking the VAT number.'));
                    $this->logger->error(
                        "Gw/AutoCustomerGroup/Model/TaxSchemes/EuVat::checkTaxId() : EuVat Error received from " .
                        "VIES. " . $e->getCode()
                    );
                    break;
            }
        }
        return $gatewayResponse;
    }

    /**
     * Return the correct REST API Base Url
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        return "https://ec.europa.eu/taxation_customs/vies/rest-api";
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
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return "EU VAT OSS/IOSS Scheme";
    }
}
