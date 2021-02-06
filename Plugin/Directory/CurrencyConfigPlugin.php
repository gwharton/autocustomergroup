<?php
namespace Gw\AutoCustomerGroup\Plugin\Directory;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Directory\Model\CurrencyConfig;

class CurrencyConfigPlugin
{
    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        AutoCustomerGroup $autoCustomerGroup,
        TaxSchemes $taxSchemes
    ) {
        $this->autoCustomerGroup = $autoCustomerGroup;
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
        if ($this->autoCustomerGroup->isCurrencyDownloadEnabled()) {
            foreach ($this->taxSchemes->getTaxSchemes() as $taxScheme) {
                $result[] = $taxScheme->getSchemeCurrencyCode();
            }
        }
        return array_unique($result);
    }
}
