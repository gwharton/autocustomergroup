<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\OrderRepository;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Sales\Order\Tax;

/**
 * Update the sales_order_tax_scheme table with details of tax scheme
 * used for order
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class TaxPlugin
{
    /**
     * @var OrderTaxSchemeFactory
     */
    private $orderTaxSchemeFactory;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;

    /**
     * @param OrderTaxSchemeFactory $orderTaxSchemeFactory
     * @param OrderRepository $orderRepository
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        OrderTaxSchemeFactory $orderTaxSchemeFactory,
        OrderRepository $orderRepository,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxRateRepositoryInterface $taxRateRepository
    ) {
        $this->orderTaxSchemeFactory = $orderTaxSchemeFactory;
        $this->orderRepository = $orderRepository;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxRateRepository = $taxRateRepository;
    }

    /**
     * @param Tax $subject
     * @param Tax $result
     * @return $this|Tax
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public function afterSave(
        Tax $subject,
        Tax $result
    ) {
        try {
            $order = $this->orderRepository->get($subject->getOrderId());
            if (!$order) {
                return $result;
            }
            $filter = $this->filterBuilder
                ->setField('code')
                ->setConditionType('eq')
                ->setValue($subject->getCode())
                ->create();
            $this->searchCriteriaBuilder->addFilters([$filter]);
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $taxRates = $this->taxRateRepository->getList($searchCriteria);
            if ($taxRates->getTotalCount() == 1) {
                //Only process if there is an exact match, and only one match
                $rate = $taxRates->getItems()[0];
                if (!$rate) {
                    return $result;
                }
                /** @var TaxSchemeInterface $taxScheme */
                $taxScheme = $rate->getExtensionAttributes()->getTaxScheme();
                if (!$taxScheme) {
                    return $result;
                }
                //OK, we have $order, $rate and $taxScheme. Thats enough.
                $storeId = $order->getStoreId();
                $percent = $subject->getPercent();
                $storeToBase = $order->getStoreToBaseRate() == 0.0 ? 1.0 : $order->getStoreToBaseRate();
                $orderItemAppliedTaxes = $order->getExtensionAttributes()->getItemAppliedTaxes();
                $taxableAmountStoreBase = 0.0;
                $taxableAmountStore = 0.0;
                $taxableAmountScheme = 0.0;
                foreach ($orderItemAppliedTaxes as $orderItemAppliedTax) {
                    foreach ($orderItemAppliedTax->getAppliedTaxes() as $appliedTax) {
                        $eatt = $appliedTax->getExtensionAttributes();
                        if ($appliedTax->getTaxId() == $subject->getCode()) {
                            $taxableAmountStoreBase += $eatt->getBaseTaxableAmount();
                            $taxableAmountStore += $eatt->getTaxableAmount();
                            $taxableAmountScheme += ($eatt->getBaseTaxableAmount() /
                                $taxScheme->getSchemeExchangeRate($storeId));
                        }
                    }
                }
                $data = [
                    'tax_id' => (int)$subject->getId(),
                    'order_id' => (int)$order->getEntityId(),
                    'reference' => $taxScheme->getSchemeRegistrationNumber($storeId),
                    'name' => $taxScheme->getSchemeName(),
                    'code' => $subject->getCode(),
                    'rate' => (float)$percent,
                    'store_currency' => $order->getOrderCurrencyCode(),
                    'store_base_currency' => $order->getBaseCurrencyCode(),
                    'scheme_currency' => $taxScheme->getSchemeCurrencyCode(),
                    'exchange_rate_store_to_store_base' => (float)$storeToBase,
                    'exchange_rate_store_base_to_scheme' => (float)$taxScheme->getSchemeExchangeRate($storeId),
                    'import_threshold_store_base' => (float)$taxScheme->getThresholdInBaseCurrency($storeId),
                    'import_threshold_store' => (float)$taxScheme->getThresholdInBaseCurrency($storeId) /
                        $storeToBase,
                    'import_threshold_scheme' => (float)$taxScheme->getThresholdInSchemeCurrency($storeId),
                    'taxable_amount_store_base' => (float)$taxableAmountStoreBase,
                    'taxable_amount_store' => (float)$taxableAmountStore,
                    'taxable_amount_scheme' => (float)$taxableAmountScheme,
                    'tax_amount_store_base' => (float)$subject->getBaseAmount(),
                    'tax_amount_store' => (float)$subject->getAmount(),
                    'tax_amount_scheme' => (float)$subject->getBaseAmount() /
                        $taxScheme->getSchemeExchangeRate($storeId)
                ];
                $orderTaxScheme = $this->orderTaxSchemeFactory->create();
                $orderTaxScheme->setData($data)->save();
            }
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (\Exception $e) {
        }
        return $result;
    }
}
