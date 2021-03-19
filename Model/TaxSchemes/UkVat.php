<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Gw\AutoCustomerGroup\Model\TaxSchemes\EuVat;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * UK VAT Test numbers for sandbox
 * https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-registered-companies-api/1.0
 * GB553557881
 * GB146295999727
 * GB948561936944
 * GB000549615108 //Isle of man
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UkVat extends AbstractTaxScheme
{
    const CODE = "ukvat";
    const SCHEME_CURRENCY = 'GBP';
    protected $code = self::CODE;

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = ['GB','IM'];

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var EuVat
     */
    private $euVat;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ClientFactory $clientFactory
     * @param Json $serializer
     * @param DateTime $datetime
     * @param LoggerInterface $logger
     * @param EuVat $euVat
     * @param StoreManagerInterface $storeManager
     * @param CurrencyFactory $currencyFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ClientFactory $clientFactory,
        Json $serializer,
        DateTime $datetime,
        LoggerInterface $logger,
        EuVat $euVat,
        StoreManagerInterface $storeManager,
        CurrencyFactory $currencyFactory
    ) {
        parent::__construct(
            $scopeConfig,
            $logger,
            $storeManager,
            $datetime,
            $currencyFactory
        );
        $this->clientFactory = $clientFactory;
        $this->serializer = $serializer;
        $this->euVat = $euVat;
    }

    /**
     * Get customer group based on Validation Result and Country of customer
     * @param string $customerCountryCode
     * @param string $customerPostCode
     * @param DataObject $vatValidationResult
     * @param Quote $quote
     * @param int|null $storeId
     * @return int|null
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $vatValidationResult,
        $quote,
        $storeId
    ) {
        $merchantCountry = $this->getMerchantCountryCode($storeId);
        $importThreshold = $this->getThresholdInBaseCurrency($this->getWebsiteIdFromStoreId($storeId));
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //Therefore Domestic
        if ($this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is in the EU
        //Item shipped to the NI
        //VAT No is valid
        //Therefore Intra-EU B2B
        if ($this->euVat->isSchemeCountry($merchantCountry) &&
            $this->isNI($customerCountryCode, $customerPostCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2b",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is in the EU
        //Item shipped to the NI
        //VAT No is not valid
        //Therefore Intra-EU B2C
        if ($this->euVat->isSchemeCountry($merchantCountry) &&
            $this->isNI($customerCountryCode, $customerPostCode) &&
            !$this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2c",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //VAT No is valid
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
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
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
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
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
     * Peform validation of the VAT number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string $vatNumber
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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

        $registrationNumber = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/registrationnumber",
            ScopeInterface::SCOPE_STORE
        );
        if (!$registrationNumber) {
            $this->logger->critical("AutoCustomerGroup::UKVat Registration Number not set.");
            return $gatewayResponse;
        }

        $client = $this->clientFactory->create();
        try {
            $response = $client->request(
                Request::HTTP_METHOD_GET,
                $this->getBaseUrl() . "/organisations/vat/check-vat-number/lookup/" .
                    str_replace([' ', '-', 'GB'], ['', '', ''], $vatNumber) . "/" .
                    str_replace([' ', '-', 'GB'], ['', '', ''], $registrationNumber),
                [
                    'headers' => [
                        'Accept' => "application/vnd.hmrc.1.0+json"
                    ]
                ]
            );
            $responseBody = $response->getBody();
            $vatRegistration = $this->serializer->unserialize($responseBody->getContents());
            $gatewayResponse->setIsValid(true);
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setRequestDate($vatRegistration['processingDate']);
            $gatewayResponse->setRequestIdentifier($vatRegistration['consultationNumber']);

            if ($gatewayResponse->getIsValid()) {
                $gatewayResponse->setRequestMessage(__('VAT Number validated with HMRC.'));
            } else {
                $gatewayResponse->setRequestMessage(__('Please enter a valid VAT number including country code.'));
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
                    $this->logger->critical("AutoCustomerGroup::UKVat Error received from HMRC. " . $e->getCode());
                    break;
            }
        }
        return $gatewayResponse;
    }

    /**
     * Return the correct REST API Base Url depending on the environment settiong
     *
     * @return string
     */
    private function getBaseUrl()
    {
        if ($this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/environment",
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        ) == Environment::ENVIRONMENT_PRODUCTION) {
            return "https://api.service.hmrc.gov.uk";
        } else {
            return "https://test-api.service.hmrc.gov.uk";
        }
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName()
    {
        return "UK VAT Scheme";
    }
}
