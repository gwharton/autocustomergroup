<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use Gw\AutoCustomerGroup\Helper\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\FlagManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

/**
 * UK VAT Test numbers for sandbox
 * https://developer.service.hmrc.gov.uk/api-documentation/docs/api/service/vat-registered-companies-api/1.0
 * GB553557881
 * GB146295999727
 * GB948561936944
 * GB000549615108 //Isle of man
 */
class UkVat extends AbstractTaxScheme
{
    const CODE = "ukvat";
    protected $code = self::CODE;

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @var DateTime
     */
    private $datetime;

    const ACCESS_TOKEN_PATH = 'autocustomergroup/hmrc/accesstoken';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ClientFactory $clientFactory
     * @param FlagManager $flagManager
     * @param Json $serializer
     * @param DateTime $datetime
     * @param LoggerInterface $logger
     * @param AutoCustomerGroup $helper
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ClientFactory $clientFactory,
        FlagManager $flagManager,
        Json $serializer,
        DateTime $datetime,
        LoggerInterface $logger,
        AutoCustomerGroup $helper
    ) {
        parent::__construct(
            $scopeConfig,
            $logger,
            $helper
        );
        $this->clientFactory = $clientFactory;
        $this->flagManager = $flagManager;
        $this->serializer = $serializer;
        $this->datetime = $datetime;
    }

    /**
     * Check if this Tax Scheme handles the requtested country
     *
     * @param string $country
     * @return bool
     */
    public function checkCountry($country)
    {
        return $this->isCountryUKIM($country);
    }

    private function isCountryUKIM($country)
    {
        return in_array($country, ['GB','IM']);
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
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //Therefore Domestic
        if ($this->isCountryUKIM($merchantCountry) &&
            $this->isCountryUKIM($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the EU
        //Item shipped to the NI
        //VAT No is valid
        //Therefore Intra-EU B2B
        if ($this->isCountryInEU($merchantCountry) &&
            $this->isNI($customerCountryCode, $customerPostCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2b",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the EU
        //Item shipped to the NI
        //VAT No is not valid
        //Therefore Intra-EU B2C
        if ($this->isCountryInEU($merchantCountry) &&
            $this->isNI($customerCountryCode, $customerPostCode) &&
            !$this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/intraeub2c",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //VAT No is valid
        //Therefore Import B2B
        if (!$this->isCountryUKIM($merchantCountry) &&
            $this->isCountryUKIM($customerCountryCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //Order value is equal or below threshold
        //Therefore Import Taxed
        if (!$this->isCountryUKIM($merchantCountry) &&
            $this->isCountryUKIM($customerCountryCode) &&
            ($this->getOrderTotal($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is in the UK/IM
        //Item shipped to the UK/IM
        //Order value is above threshold
        //Therefore Import Unaxed
        if (!$this->isCountryUKIM($merchantCountry) &&
            $this->isCountryUKIM($customerCountryCode) &&
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

        $accesstoken = $this->loadAccessToken();
        if (!$accesstoken) {
            $accesstoken = $this->getAccessToken();
        }
        if (!$accesstoken) {
            $this->logger->critical("AutoCustomerGroup::UKVat No Access Token.");
            return $gatewayResponse;
        }

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
                        'Accept' => "application/vnd.hmrc.1.0+json",
                        "Authorization" => 'Bearer ' . $accesstoken['access_token']
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
     * Load access token from the flag database if one exists
     *
     * @return void|array
     */
    private function loadAccessToken()
    {
        if (!$this->flagManager->getFlagData(self::ACCESS_TOKEN_PATH)) {
            return null;
        }
        $accesstoken = $this->serializer->unserialize(
            $this->flagManager->getFlagData(self::ACCESS_TOKEN_PATH)
        );
        //Has access token expired
        if (($accesstoken['access_token_issue_time'] + $accesstoken['expires_in'] - 10) <
            $this->datetime->gmtTimestamp()
        ) {
            return null;
        }
        return $accesstoken;
    }

    /**
     * Retrieve new access token from HMRC server
     *
     * @return void|array
     */
    private function getAccessToken()
    {
        $clientId = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/clientid",
            ScopeInterface::SCOPE_STORE
        );
        $clientSecret = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/clientsecret",
            ScopeInterface::SCOPE_STORE
        );
        if (!$clientId && !$clientSecret) {
            $this->logger->critical("AutoCustomerGroup::UKVat ClientID and ClientSecret are not set.");
            return null;
        }
        $client = $this->clientFactory->create();
        try {
            $response = $client->request(
                Request::HTTP_METHOD_POST,
                $this->getBaseUrl() . "/oauth/token",
                [
                    'headers' => [
                        'Content-Type' => "application/x-www-form-urlencoded"
                    ],
                    'body' =>   'client_secret=' . $clientSecret . '&' .
                                'client_id=' . $clientId . '&' .
                                'grant_type=client_credentials&'
                ]
            );
            $responseBody = $response->getBody();
            $accesstoken = $this->serializer->unserialize($responseBody->getContents());
            $ts = $this->datetime->gmtTimestamp();
            $accesstoken['access_token_issue_time'] = $ts;
            $this->flagManager->saveFlag(self::ACCESS_TOKEN_PATH, $this->serializer->serialize($accesstoken));
            return $accesstoken;
        } catch (BadResponseException $e) {
            $this->logger->critical("AutoCustomerGroup::UKVat Unable to get Access Token from HMRC.");
            return null;
        }
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
}
