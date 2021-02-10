<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRuleSearchResultsInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation\Rate;

class TaxRateExtensionAttributesPlugin
{
    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        TaxSchemes $taxSchemes
    ) {
        $this->taxSchemes = $taxSchemes;
    }

    /**
     * Restore the value of tax_scheme_id to extension attribute
     *
     * @param TaxRateRepositoryInterface $subject
     * @param Rate $result
     * @param int $rateId
     * @return TaxRateInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        TaxRateRepositoryInterface $subject,
        Rate $result,
        int $rateId
    ) {
        $taxSchemeId = $result->getData('tax_scheme_id');
        $taxScheme = $this->taxSchemes->getTaxScheme($taxSchemeId);
        if ($taxScheme) {
            $extensionAttributes = $result->getExtensionAttributes();
            $extensionAttributes->setTaxScheme($this->taxSchemes->getTaxScheme($taxSchemeId));
            $result->setExtensionAttributes($extensionAttributes);
        }
        return $result;
    }

    /**
     * Save the value of tax_scheme_id from extension attribute
     *
     * @param TaxRateRepositoryInterface $subject
     * @param TaxRateInterface $entity
     * @return TaxRateInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        TaxRateRepositoryInterface $subject,
        Rate $entity
    ) {
        $extensionAttributes = $entity->getExtensionAttributes();
        $taxScheme = $extensionAttributes->getTaxScheme();
        if ($taxScheme) {
            $entity->setData('tax_scheme_id', $taxScheme->getSchemeId());
        } else {
            $entity->setData('tax_scheme_id', null);
        }
        return [$entity];
    }

    /**
     * Restore the value of tax_scheme_id to extension attribute
     *
     * Why $tresult as TaxRuleSearchResultsInterface No idea. Tax Rate Admin Grid
     * bugs out if I use TaxRateSearchResultsInterface. Suspect Magento Bug
     *
     * @param TaxRateRepositoryInterface $subject
     * @param TaxRuleSearchResultsInterface $result
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        TaxRateRepositoryInterface $subject,
        TaxRuleSearchResultsInterface $result
    ) {
        $taxRates = [];
        /** @var Rate $entity */
        foreach ($result->getItems() as $entity) {
            $taxSchemeId = $entity->getData('tax_scheme_id');
            $taxScheme = $this->taxSchemes->getTaxScheme($taxSchemeId);
            if ($taxScheme) {
                $extensionAttributes = $entity->getExtensionAttributes();
                $extensionAttributes->setTaxScheme($taxScheme);
                $entity->setExtensionAttributes($extensionAttributes);
            }
            $taxRates[] = $entity;
        }
        $result->setItems($taxRates);
        return $result;
    }
}
