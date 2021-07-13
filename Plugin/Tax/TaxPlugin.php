<?php
namespace Gw\AutoCustomerGroup\Plugin\Tax;

use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\OrderRepository;
use Magento\Tax\Api\TaxRateRepositoryInterface;

/**
 * Update the sales_order_tax_scheme table with details of tax scheme
 * used for order
 */
class TaxPlugin
{
    /**
     * @var \Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory
     */
    private $orderTaxSchemeFactory;

    /**
     * @var OrderRepository $orderRepository
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
     * @param \Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory $orderTaxSchemeFactory
     */
    public function __construct(
        \Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory $orderTaxSchemeFactory,
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

    public function afterSave(
        \Magento\Tax\Model\Sales\Order\Tax $subject,
        \Magento\Tax\Model\Sales\Order\Tax $result
    ) {
        try {
            $order = $this->orderRepository->get($subject->getOrderId());
            if (!$order) {
                return $this;
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
                    return $this;
                }
                /** @var TaxSchemeInterface $taxScheme */
                $taxScheme = $rate->getExtensionAttributes()->getTaxScheme();
                if (!$taxScheme) {
                    return $this;
                }
                //OK, we have $order, $rate and $taxScheme. Thats enough.
                $storeId = $order->getStoreId();
                $percent = $subject->getPercent();
                $storeToBase = $order->getStoreToBaseRate() == 0.0 ? 1.0 : $order->getStoreToBaseRate();
                $data = [
                    'tax_id' => $subject->getId(),
                    'order_id' => $order->getEntityId(),
                    'reference' => $taxScheme->getSchemeRegistrationNumber($storeId),
                    'name' => $taxScheme->getSchemeName(),
                    'code' => $subject->getCode(),
                    'rate' => $percent,
                    'store_currency' => $order->getOrderCurrencyCode(),
                    'store_base_currency' => $order->getBaseCurrencyCode(),
                    'scheme_currency' => $taxScheme->getSchemeCurrencyCode(),
                    'exchange_rate_store_to_store_base' => $storeToBase,
                    'exchange_rate_store_base_to_scheme' => (float)$taxScheme->getSchemeExchangeRate($storeId),
                    'import_threshold_store_base' => $taxScheme->getThresholdInBaseCurrency($storeId),
                    'import_threshold_store' => $taxScheme->getThresholdInBaseCurrency($storeId) / $storeToBase,
                    'import_threshold_scheme' => (float)$taxScheme->getThresholdInSchemeCurrency($storeId),
                    'taxable_amount_store_base' => $subject->getBaseAmount() * $percent,
                    'taxable_amount_store' => $subject->getAmount()  * $percent,
                    'taxable_amount_scheme' => $subject->getBaseAmount() * $percent /
                        $taxScheme->getSchemeExchangeRate($storeId),
                    'tax_amount_store_base' => $subject->getBaseAmount(),
                    'tax_amount_store' => $subject->getAmount(),
                    'tax_amount_scheme' => $subject->getBaseAmount() / $taxScheme->getSchemeExchangeRate($storeId)
                ];

                /** @var $orderTaxScheme \Gw\AutoCustomerGroup\Model\OrderTaxScheme */
                $orderTaxScheme = $this->orderTaxSchemeFactory->create();
                $orderTaxScheme->setData($data)->save();
            }
        } catch ( \Exception $e) {

        }
        return $subject;
    }
}
