<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Store\Model\StoreManagerInterface;

class JSConfig
{
    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param StoreManagerInterface $storeManager
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        AutoCustomerGroup $autoCustomerGroup,
        StoreManagerInterface $storeManager,
        TaxSchemes $taxSchemes
    ) {
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->storeManager = $storeManager;
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * The config for the JS VAT Component
     *
     * @return array
     */
    public function getComponentConfig()
    {
        $storeId = $this->storeManager->getStore()->getId();
        if ($this->autoCustomerGroup->isModuleEnabled($storeId)) {
            $jsSchemes = [];

            foreach ($this->taxSchemes->getEnabledTaxSchemes($storeId) as $taxScheme) {
                $jsSchemes[$taxScheme->getSchemeId()] = [
                    'countries' => $taxScheme->getSchemeCountries(),
                    'prompt' => $taxScheme->getFrontEndPrompt($storeId)
                ];
            }
            return [
                'template' => 'Gw_AutoCustomerGroup/tax-id-element',
                'label' => $this->autoCustomerGroup->getFrontendLabel($storeId),
                'additionalClasses' => 'autocustomergroup_tax_id',
                'delay' => 1500,
                'validationEnabled' => $this->autoCustomerGroup->isModuleEnabled($storeId),
                'schemes' => $jsSchemes,
                'storeId' => $storeId,
                'component' => 'Gw_AutoCustomerGroup/js/tax-id-element'
            ];
        } else {
            //Legacy config if module is disabled
            return [];
        }
    }
}
