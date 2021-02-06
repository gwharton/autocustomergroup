<?php
namespace Gw\AutoCustomerGroup\Plugin\Directory;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Customer\Helper\Address as AddressHelper;
use Magento\Directory\Model\CurrencyConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CurrencyConfigPlugin
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var AddressHelper
     */
    private $customerAddressHelper;

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param AddressHelper $customerAddressHelper
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        AddressHelper $customerAddressHelper,
        TaxSchemes $taxSchemes
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->customerAddressHelper = $customerAddressHelper;
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * Add the scheme currencies to the list of base currencies. This ensures that
     * currency downloads will be performed for all scheme currencies.
     *
     * @param CurrencyConfig $subject
     * @param array $result
     * @param string $path
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfigCurrencies(
        CurrencyConfig $subject,
        array $result,
        string $path
    ) {
        if ($this->customerAddressHelper->isVatValidationEnabled() &&
            $this->scopeConfig->isSetFlag(
                "autocustomergroup/general/enablecurrencydownload",
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            )) {
            foreach ($this->taxSchemes->getEnabledTaxSchemes() as $taxScheme) {
                $result[] = $taxScheme->getSchemeCurrencyCode();
            }
        }
        return array_unique($result);
    }
}
