<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Customer\Helper\Address;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class JSConfig
{
    const XML_PATH_FRONTEND_LABEL = 'autocustomergroup/general/frontendlabel';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Address
     */
    private $addressHelper;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Address $addressHelper
     * @param StoreManagerInterface $storeManager
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Address $addressHelper,
        StoreManagerInterface $storeManager,
        TaxSchemes $taxSchemes
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->addressHelper = $addressHelper;
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
        $jsSchemes = [];
        foreach ($this->taxSchemes->getEnabledTaxSchemes() as $taxScheme) {
            $jsSchemes[$taxScheme->getSchemeId()] = [
                'countries' => $taxScheme->getSchemeCountries(),
                'prompt' => $taxScheme->getFrontEndPrompt()
            ];
        }
        return [
            'template' => 'Gw_AutoCustomerGroup/tax-id-element',
            'label' => $this->geFrontendLabel(),
            'additionalClasses' => 'autocustomergroup_tax_id',
            'delay' => 1500,
            'validationEnabled' => $this->addressHelper->isVatValidationEnabled($this->storeManager->getStore()),
            'schemes' => $jsSchemes
        ];
    }

    /**
     * Retrieve Frontend Control Label
     *
     * @param Store|string|int|null $store
     * @return string
     */
    public function geFrontendLabel($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FRONTEND_LABEL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
