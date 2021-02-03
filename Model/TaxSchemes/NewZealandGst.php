<?php
namespace Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class NewZealandGst extends AbstractTaxScheme
{
    const CODE = "newzealandgst";
    protected $code = self::CODE;

    /**
     * Check if this Tax Scheme handles the requtested country
     *
     * @param string $country
     * @return bool
     */
    public function checkCountry($country)
    {
        return $this->isCountryNewZealand($country);
    }

    private function isCountryNewZealand($country)
    {
        return in_array($country, ['NZ']);
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
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
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
        //Merchant Country is in New Zealand
        //Item shipped to New Zealand
        //Therefore Domestic
        if ($this->isCountryNewZealand($merchantCountry) &&
            $this->isCountryNewZealand($customerCountryCode)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/domestic",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //GST Number Supplied
        //Therefore Import B2B
        if (!$this->isCountryNewZealand($merchantCountry) &&
            $this->isCountryNewZealand($customerCountryCode) &&
            $this->isValid($vatValidationResult)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importb2b",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //All items equal or below threshold
        //Therefore Import Taxed
        if (!$this->isCountryNewZealand($merchantCountry) &&
            $this->isCountryNewZealand($customerCountryCode) &&
            ($this->getMostExpensiveItem($quote) <= $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importtaxed",
                ScopeInterface::SCOPE_STORE,
                $store
            );
        }
        //Merchant Country is not in New Zealand
        //Item shipped to New Zealand
        //Any item is above threshold
        //Therefore Import Unaxed
        if (!$this->isCountryNewZealand($merchantCountry) &&
            $this->isCountryNewZealand($customerCountryCode) &&
            ($this->getMostExpensiveItem($quote) > $importThreshold)) {
            return $this->scopeConfig->getValue(
                "autocustomergroup/" . self::CODE . "/importuntaxed",
                ScopeInterface::SCOPE_STORE,
                $store
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
            $this->isCountryNewZealand($countryCode) &&
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
