<?php
namespace Gw\AutoCustomerGroup\Block\Adminhtml;

use Gw\AutoCustomerGroup\Model\TaxSchemes\AbstractTaxScheme;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class ThresholdSummary extends Field
{
    /**
     * @var AbstractTaxScheme
     */
    private $taxScheme;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param TaxScheme $taxScheme
     * @param ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        AbstractTaxScheme $taxScheme,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $data
        );
        $this->taxScheme = $taxScheme;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve element HTML markup
     *
     * @param AbstractElement $element
     * @return string
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $storeCurrency = $this->scopeConfig->getValue(
            "currency/options/default",
            ScopeInterface::SCOPE_STORE
        );
        $thresholdInStoreCurrency = $this->taxScheme->getThresholdInStoreCurrency();
        return '<div class="thresholdsummary-wrapper">' .
            '<div>' . sprintf("%.2f", $thresholdInStoreCurrency) . ' ' . $storeCurrency . '</div>' .
            '</div>';
    }
}
