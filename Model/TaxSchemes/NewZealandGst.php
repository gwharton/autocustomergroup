<?php

namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Gw\AutoCustomerGroup\Model\Config\Source\Environment;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterfaceFactory;

/**
 * New Zealand NZBN Test numbers for Sandbox (as of 11/07/2024)
 * 9429032097351 - MICROSOFT NEW ZEALAND LIMITED - NO GST
 * 9429036975273 - GOOGLE NEW ZEALAND LIMITED - NO GST
 * 9429050853731 - TRACY'S TEST COMPANY LIMITED - GST 111111111
 * 9429049835892 - WOF 00916825 LIMITED - GST 111111111
 *
 * Real New Zealand numbers (as of 11/07/2024)
 * 9429039098740 - MICROSOFT NEW ZEALAND LIMITED - NO GST
 * 9429034243282 - GOOGLE NEW ZEALAND LIMITED - NO GST
 * 9429041535110 - AMAZON SERVICES LIMITED - GST 115706691
 * 9429049999198 - G.S.GILL. LIMITED - GST 134953500
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NewZealandGst extends AbstractTaxScheme
{
    const CODE = "newzealandgst";
    const SCHEME_CURRENCY = 'NZD';
    protected $code = self::CODE;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = ['NZ'];

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
                "Gw/AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::getCustomerGroup() : " .
                "Merchant country not set."
            );
            return null;
        }
        $importThreshold = $this->getThresholdInBaseCurrency($storeId);
        //Merchant Country is in New Zealand
        //Item shipped to New Zealand
        //Therefore Domestic
        if ($this->isSchemeCountry($merchantCountry) &&
            $this->isSchemeCountry($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //GST Number Supplied
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
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
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
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
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
     * Peform validation of the NZBN Number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string|null $taxId
     * @return GatewayResponseInterface
     * @throws GuzzleException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function checkTaxId(
        string $countryCode,
        ?string $taxId
    ): GatewayResponseInterface {
        $gatewayResponse = $this->gwrFactory->create();
        $gatewayResponse->setRequestMessage(__('Error during NZBN Number verification.'));

        if (!preg_match("/^[0-9]{13}$/", $taxId) ||
            !$this->isSchemeCountry($countryCode) ||
            !$this->isValidNzbn($taxId)) {
            $gatewayResponse->setRequestMessage(__('NZBN Number is not the correct format.'));
            return $gatewayResponse;
        }

        if (!$this->scopeConfig->isSetFlag(
            "autocustomergroup/" . $this->getSchemeId() . '/validate_online',
            ScopeInterface::SCOPE_STORE
        )) {
            $gatewayResponse->setRequestMessage(__('NZBN Number is the correct format.'));
            $gatewayResponse->setIsValid(true);
            $gatewayResponse->setRequestSuccess(true);
            return $gatewayResponse;
        }

        $accesstoken = $this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/accesstoken",
            ScopeInterface::SCOPE_STORE
        );
        if (!$accesstoken) {
            $this->logger->critical(
                "AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : " .
                "No Access Token"
            );
            return $gatewayResponse;
        }

        $client = $this->clientFactory->create();
        try {
            $response = $client->request(
                Request::HTTP_METHOD_GET,
                $this->getBaseUrl() . "/entities/" . $taxId . "/gst-numbers",
                [
                    'headers' => [
                        "Ocp-Apim-Subscription-Key" => $accesstoken,
                        'Accept' => "application/json"
                    ]
                ]
            );
            $responseBody = $response->getBody();
            $registrations = $this->serializer->unserialize($responseBody->getContents());
            if (!is_array($registrations)) {
                $gatewayResponse->setIsValid(false);
                $gatewayResponse->setRequestSuccess(false);
                $gatewayResponse->setRequestMessage(__('Error communicating with NZBN API.'));
                $this->logger->error(
                    "Gw/AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : Could not interpret " .
                    " response",
                    ['registration' => $registrations]
                );
                return $gatewayResponse;
            }
            $gatewayResponse->setRequestMessage(__('GST Number not registered at the NZBN Register for this NZBN.'));
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setIsValid(false);
            foreach ($registrations as $registration) {
                $identifier = $registration['uniqueIdentifier'];
                $gst = $registration['gstNumber'];
                $startDate = $registration['startDate'];
                $now = $this->datetime->gmtDate();
                if ($startDate <= $now && $this->isValidGst($gst)) {
                    $gatewayResponse->setRequestMessage(
                        __('GST Registration Number ' . $gst . ' is validated for NZBN ' . $taxId)
                    );
                    $gatewayResponse->setIsValid(true);
                    $gatewayResponse->setRequestDate($now);
                    $gatewayResponse->setRequestIdentifier($identifier);
                    break;
                }
            }
        } catch (BadResponseException $e) {
            switch ($e->getCode()) {
                case 404:
                    $gatewayResponse->setIsValid(false);
                    $gatewayResponse->setRequestSuccess(true);
                    $gatewayResponse->setRequestMessage(__('NZBN Number not found.'));
                    break;
                default:
                    $gatewayResponse->setIsValid(false);
                    $gatewayResponse->setRequestSuccess(false);
                    $gatewayResponse->setRequestMessage(__('There was an error checking the NZBN number.'));
                    $this->logger->error(
                        "Gw/AutoCustomerGroup/Model/TaxSchemes/NewZealandGst::checkTaxId() : Error received " .
                        "from Server. " . $e->getCode() . " " . $e->getMessage()
                    );
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
    private function getBaseUrl(): string
    {
        if ($this->scopeConfig->getValue(
            "autocustomergroup/" . self::CODE . "/environment",
            ScopeInterface::SCOPE_STORE
        ) == Environment::ENVIRONMENT_PRODUCTION) {
            return "https://api.business.govt.nz/gateway/nzbn/v5";
        } else {
            return "https://api.business.govt.nz/sandbox/nzbn/v5";
        }
    }

    /**
     * Validate an GST Number
     *
     * https://wiki.scn.sap.com/wiki/display/CRM/New+Zealand
     *
     * @param string|null $gst
     * @return bool
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isValidGst(?string $gst): bool
    {
        $weightsa = [3,2,7,6,5,4,3,2];
        $weightsb = [7,4,3,2,5,2,7,6];
        $gst = preg_replace('/[^0-9]/', '', $gst ?? "");
        if (!is_numeric($gst)) {
            return false;
        }
        $check = $gst[strlen($gst)-1];
        $withoutcheck = substr($gst, 0, strlen($gst)-1);
        if (strlen($withoutcheck) == 7) {
            $withoutcheck = "0".$withoutcheck;
        }
        if (strlen($withoutcheck) != 8) {
            return false;
        }
        $sum = 0;
        foreach (str_split($withoutcheck) as $key => $digit) {
            $sum += ((int) $digit * $weightsa[$key]);
        }
        $remainder = $sum % 11;
        if ($remainder == 0) {
            $calculatedCheck = $remainder;
        } else {
            $calculatedCheck = 11 - $remainder;
            if ($calculatedCheck == 10) {
                $sum = 0;
                foreach (str_split($withoutcheck) as $key => $digit) {
                    $sum += ((int) $digit * $weightsb[$key]);
                }
                $remainder = $sum % 11;
                if ($remainder == 0) {
                    $calculatedCheck = $remainder;
                } else {
                    $calculatedCheck = 11 - $remainder;
                    if ($calculatedCheck == 10) {
                        return false;
                    }
                }
            }
        }
        if ($calculatedCheck == $check) {
            return true;
        }
        return false;
    }

    /**
     * Validate an NZBN Number
     *
     * https://www.gs1.org/services/how-calculate-check-digit-manually
     *
     * @param string|null $nzbn
     * @return bool
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isValidNzbn(?string $nzbn): bool
    {
        $weights = [1,3,1,3,1,3,1,3,1,3,1,3];

        if (!is_numeric($nzbn)) {
            return false;
        }
        if (strlen($nzbn) != 13) {
            return false;
        }
        $check = $nzbn[strlen($nzbn)-1];
        $withoutcheck = substr($nzbn, 0, strlen($nzbn)-1);

        $sum = 0;
        foreach (str_split($withoutcheck) as $key => $digit) {
            $sum += ((int) $digit * $weights[$key]);
        }
        $remainder = $sum % 10;
        $calculatedCheck = 10 - $remainder;
        if ($calculatedCheck == $check) {
            return true;
        }
        return false;
    }

    /**
     * Get the scheme name
     *
     * @return string
     */
    public function getSchemeName(): string
    {
        return "New Zealand GST Scheme";
    }
}
