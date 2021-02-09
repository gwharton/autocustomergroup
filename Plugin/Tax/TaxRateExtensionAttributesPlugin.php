<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation\Rate;

class TaxRateExtensionAttributesPlugin
{
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
        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setTaxSchemeId($result->getData('tax_scheme_id'));
        $result->setExtensionAttributes($extensionAttributes);
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
        $taxSchemeId = $extensionAttributes->getTaxSchemeId();
        /** @var Rate $entity */
        $entity->setData('tax_scheme_id', $taxSchemeId);
        return [$entity];
    }
}
