<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes\AbstractTaxScheme;

class TaxSchemes
{
    /**
     * @var AbstractTaxScheme[]
     */
    private $taxSchemes = [];

    /**
     * @param AbstractTaxScheme[] $taxSchemes
     */
    public function __construct(
        array $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * Get available Tax Schemes
     *
     * @return AbstractTaxScheme[]
     */
    public function getTaxSchemes()
    {
        return $this->taxSchemes;
    }

    /**
     * Get list of enabled tax schemes
     *
     * @return AbstractTaxScheme[]
     */
    public function getEnabledTaxSchemes()
    {
        $enabledSchemes = [];
        foreach ($this->taxSchemes as $taxScheme) {
            if ($taxScheme->isEnabled()) {
                $enabledSchemes[] = $taxScheme;
            }
        }
        return $enabledSchemes;
    }
}
