<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;

class TaxSchemes
{
    /**
     * @var TaxSchemeInterface[]
     */
    private $taxSchemes = [];

    /**
     * @param TaxSchemeInterface[] $taxSchemes
     */
    public function __construct(
        array $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * Get available Tax Schemes
     *
     * @return TaxSchemeInterface[]
     */
    public function getTaxSchemes(): array
    {
        return $this->taxSchemes;
    }

    /**
     * Get list of enabled tax schemes
     *
     * @param int|null $storeId
     * @return TaxSchemeInterface[]
     */
    public function getEnabledTaxSchemes($storeId): array
    {
        $enabledSchemes = [];
        foreach ($this->taxSchemes as $taxScheme) {
            if ($taxScheme->isEnabled($storeId)) {
                $enabledSchemes[] = $taxScheme;
            }
        }
        return $enabledSchemes;
    }

    /**
     * return list of Tax Schemes as option array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $array = [];
        foreach ($this->taxSchemes as $taxScheme) {
            $array[] = ['value' => $taxScheme->getSchemeId(), 'label' => $taxScheme->getSchemeName()];
        }
        return $array;
    }

    /**
     * Get the instance of the tax scheme by code
     *
     * @param string $code
     * @return null|TaxSchemeInterface
     */
    public function getTaxScheme($code): ?TaxSchemeInterface
    {
        return isset($this->taxSchemes[$code]) ? $this->taxSchemes[$code] : null;
    }
}
