<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

class AutoCustomerGroup
{
    const XML_PATH_FRONTEND_LABEL = 'autocustomergroup/general/frontendlabel';
    const XML_PATH_ENABLE_CURRENCY_DOWNLOAD = 'autocustomergroup/general/enablecurrencydownload';
    const XML_PATH_MODULE_ENABLED = 'autocustomergroup/general/enabled';
    const XML_PATH_VALIDATE_ON_EACH = 'autocustomergroup/general/validate_on_each_transaction';

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param TaxSchemes $taxSchemes
     * @param ScopeConfigInterface $scopeConfig
     * @param Calculation $calculation
     * @param GroupRepositoryInterface $groupRepository
     * @param ResourceCalculation $resourceCalculation
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        TaxSchemes $taxSchemes,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->taxSchemes = $taxSchemes;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param string $countryCode
     * @param string $taxId
     * @param int|null $storeId
     * @return DataObject|null
     */
    public function checkTaxId(
        $countryCode,
        $taxId,
        $storeId
    ) {
        if ($this->isModuleEnabled($storeId)) {
            foreach ($this->taxSchemes->getEnabledTaxSchemes($storeId) as $taxScheme) {
                if ($taxScheme->isSchemeCountry($countryCode)) {
                    return $taxScheme->checkTaxId($countryCode, $taxId);
                }
            }
        }
        return null;
    }

    /**
     * @param string $customerCountryCode
     * @param string $customerPostCode
     * @param DataObject $taxIdValidationResults
     * @param Quote $quote
     * @param int $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        $customerCountryCode,
        $customerPostCode,
        $taxIdValidationResults,
        $quote,
        $storeId
    ) {
        if ($this->isModuleEnabled($storeId)) {
            foreach ($this->taxSchemes->getEnabledTaxSchemes($storeId) as $taxScheme) {
                if ($taxScheme->isSchemeCountry($customerCountryCode)) {
                    return $taxScheme->getCustomerGroup(
                        $customerCountryCode,
                        $customerPostCode,
                        $taxIdValidationResults,
                        $quote,
                        $storeId
                    );
                }
            }
        }
        return null;
    }

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId
     * @return boolean
     */
    public function isModuleEnabled($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MODULE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Is currency download enabled for scheme currencies
     *
     * @return boolean
     */
    public function isCurrencyDownloadEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_CURRENCY_DOWNLOAD,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
    }

    /**
     * Retrieve Frontend Control Label
     *
     * @param int $storeId
     * @return string
     */
    public function getFrontendLabel($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FRONTEND_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Is Validation on each Transaction Enabled
     *
     * @param int $storeId
     * @return string
     */
    public function isValidateOnEachTransactionEnabled($storeId)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_VALIDATE_ON_EACH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
