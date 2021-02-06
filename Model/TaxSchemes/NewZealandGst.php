<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class NewZealandGst extends AbstractTaxScheme
{
    const CODE = "newzealandgst";
    const SCHEME_CURRENCY = 'NZD';
    protected $code = self::CODE;

    /**
     * Array of country ID's that this scheme supports
     *
     * @var string[]
     */
    protected $schemeCountries = ['NZ'];

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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
     * Peform validation of the GST Number, returning a gatewayResponse object
     *
     * @param string $countryCode
     * @param string $gst
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function checkTaxId($countryCode, $gst)
    {
        $gatewayResponse = new DataObject([
            'is_valid' => false,
            'request_date' => '',
            'request_identifier' => '',
            'request_success' => false,
            'request_message' => __('Error during GST Number verification.'),
        ]);

        $sanitisedGst = str_replace([' ', '-'], ['', ''], $gst);

        if (preg_match("/^[0-9]{8,9}$/", $sanitisedGst) &&
            $this->isSchemeCountry($countryCode) &&
            $this->isValidGst($sanitisedGst)) {
            $gatewayResponse->setIsValid(true);
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setRequestMessage(__('GST Number is the correct format.'));
        } else {
            $gatewayResponse->setIsValid(false);
            $gatewayResponse->setRequestSuccess(true);
            $gatewayResponse->setRequestMessage(__('GST Number is not the correct format.'));
        }
        return $gatewayResponse;
    }

    /**
     * Validate an GST Number (GST)
     *
     * https://wiki.scn.sap.com/wiki/display/CRM/New+Zealand
     *
     * @param string $gst
     * @return bool
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function isValidGst($gst)
    {
        $weightsa = [3,2,7,6,5,4,3,2];
        $weightsb = [7,4,3,2,5,2,7,6];
        $gst = preg_replace('/[^0-9]/', '', $gst);
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
}
