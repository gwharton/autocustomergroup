<?php
namespace Gw\AutoCustomerGroup\Model;

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
    const XML_PATH_SALES_ORDER_TAX_SCHEME = 'autocustomergroup/general/enable_sales_order_tax_scheme_table';
    const XML_PATH_DEFAULT_GROUP = 'autocustomergroup/general/default_customer_group';

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
     * @param int $storeId
     * @return DataObject|null
     */
    public function checkTaxId(
        string $countryCode,
        string $taxId,
        int $storeId
    ): ?DataObject {
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
     * @param DataObject $validationResults
     * @param Quote $quote
     * @param int $storeId
     * @return int|null
     */
    public function getCustomerGroup(
        string $customerCountryCode,
        string $customerPostCode,
        DataObject $validationResults,
        Quote $quote,
        int $storeId
    ): ?int {
        if ($this->isModuleEnabled($storeId)) {
            foreach ($this->taxSchemes->getEnabledTaxSchemes($storeId) as $taxScheme) {
                if ($taxScheme->isSchemeCountry($customerCountryCode)) {
                    return $taxScheme->getCustomerGroup(
                        $customerCountryCode,
                        $customerPostCode,
                        $validationResults,
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
    public function isModuleEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_MODULE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if sales_order_tax_scheme table is enabled
     *
     * @param int|null $storeId
     * @return boolean
     */
    public function isSalesOrderTaxSchemeEnabled(?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SALES_ORDER_TAX_SCHEME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Is currency download enabled for scheme currencies
     *
     * @return boolean
     */
    public function isCurrencyDownloadEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_CURRENCY_DOWNLOAD
        );
    }

    /**
     * Retrieve Frontend Control Label
     *
     * @param int $storeId
     * @return string
     */
    public function getFrontendLabel(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FRONTEND_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retrieve Default Group
     *
     * @param int $storeId
     * @return int
     */
    public function getDefaultGroup(int $storeId): int
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_GROUP,
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
    public function isValidateOnEachTransactionEnabled(int $storeId): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_VALIDATE_ON_EACH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
