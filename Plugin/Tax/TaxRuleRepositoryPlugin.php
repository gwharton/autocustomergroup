<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Tax\Api\Data\TaxRuleSearchResultsInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\Tax\Model\TaxRuleRepository;

class TaxRuleRepositoryPlugin
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
     * @param TaxRuleRepository $subject
     * @param Rule $result
     * @param int $ruleId
     * @return Rule
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGet(
        TaxRuleRepository $subject,
        Rule $result,
        int $ruleId
    ): Rule {
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
     * @param TaxRuleRepository $subject
     * @param Rule $entity
     * @return Rule[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        TaxRuleRepository $subject,
        Rule $entity
    ): array {
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
     * @param TaxRuleRepository $subject
     * @param TaxRuleSearchResultsInterface $result
     * @param SearchCriteriaInterface $searchCriteria
     * @return TaxRuleSearchResultsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        TaxRuleRepository $subject,
        TaxRuleSearchResultsInterface $result,
        SearchCriteriaInterface $searchCriteria
    ) {
        $taxRules = [];
        /** @var Rule $entity */
        foreach ($result->getItems() as $entity) {
            $taxSchemeId = $entity->getData('tax_scheme_id');
            $taxScheme = $this->taxSchemes->getTaxScheme($taxSchemeId);
            if ($taxScheme) {
                $extensionAttributes = $entity->getExtensionAttributes();
                $extensionAttributes->setTaxScheme($taxScheme);
                $entity->setExtensionAttributes($extensionAttributes);
            }
            $taxRules[] = $entity;
        }
        $result->setItems($taxRules);
        return $result;
    }
}
