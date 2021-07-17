<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Tax\Api\Data\TaxRuleInterface;
use Magento\Tax\Api\Data\TaxRuleSearchResultsInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\Tax\Model\TaxRuleRepository;

/**
 * Ensure that the TaxScheme extension attribute is loaded and saved by the
 * TaxRuleRepository.
 */
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
        $result->unsetData('tax_scheme_id');
        return $result;
    }

    /**
     * Save the value of tax_scheme_id from extension attribute
     *
     * @param TaxRuleRepository $subject
     * @param TaxRuleInterface $entity
     * @return TaxRuleInterface[]
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(
        TaxRuleRepository $subject,
        TaxRuleInterface $entity
    ): array {
        $extensionAttributes = $entity->getExtensionAttributes();
        $taxScheme = $extensionAttributes->getTaxScheme();
        /** @var Rule $entity */
        if ($taxScheme) {
            $entity->setData('tax_scheme_id', $taxScheme->getSchemeId());
        } else {
            $entity->setData('tax_scheme_id');
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
    ): TaxRuleSearchResultsInterface {
        $taxRules = [];
        foreach ($result->getItems() as $entity) {
            /** @var Rule $entity */
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
