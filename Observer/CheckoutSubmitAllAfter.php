<?php
namespace Gw\AutoCustomerGroup\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Tax\Model\TaxRuleRepository;
use Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory;

class CheckoutSubmitAllAfter implements ObserverInterface
{
    /**
     * @var TaxRuleRepository
     */
    private $taxRuleRepository;

    /**
     * @var OrderTaxSchemeFactory
     */
    private $orderTaxSchemeFactory;

    /**
     * @param TaxRuleRepository $taxRuleRepository
     */
    public function __construct(
        TaxRuleRepository $taxRuleRepository,
        OrderTaxSchemeFactory $orderTaxSchemeFactory
    ) {
        $this->taxRuleRepository = $taxRuleRepository;
        $this->orderTaxSchemeFactory = $orderTaxSchemeFactory;
    }
    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        //Loop through the applied taxes on the order and extract the Tax Rule IDs that have been triggered
        /** @var Order $order */
        $order = $observer->getOrder();
        $orderEA = $order->getExtensionAttributes();
        $orderrules = [];
        if ($orderEA) {
            $appliedTaxes = $orderEA->getAppliedTaxes();
            if ($appliedTaxes) {
                foreach ($appliedTaxes as $appliedTax) {
                    $appliedTaxEA = $appliedTax->getExtensionAttributes();
                    if ($appliedTaxEA) {
                        $rates = $appliedTaxEA->getRates();
                        if ($rates) {
                            foreach ($rates as $rate) {
                                $ratesEA = $rate->getExtensionAttributes();
                                if ($ratesEA) {
                                    $orderrules = array_unique(
                                        array_merge($orderrules, $ratesEA->getTaxRuleIds() ?? [])
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        //Loop through the Tax Rules that have been triggered and extract the Tax Schemes Linked to those rules.
        $taxSchemes = [];
        foreach ($orderrules as $orderrule) {
            try {
                $taxRule = $this->taxRuleRepository->get($orderrule);
                $taxRuleEA = $taxRule->getExtensionAttributes();
                if ($taxRuleEA && $taxRuleEA->getTaxScheme()) {
                    $taxSchemes[] = $taxRuleEA->getTaxScheme();
                }
            } catch (\Exception $e) {
                //Could not load Tax Rule
            }
        }
        $taxSchemes = array_unique($taxSchemes);

        //Save the tax scheme info in the sales_order_tax_scheme table.
        foreach ($taxSchemes as $taxScheme) {
            $storeId = $order->getStoreId();
            $storeToBase = $order->getStoreToBaseRate() == 0.0 ? 1.0 : $order->getStoreToBaseRate();
            $data = [
                'order_id' => (int)$order->getEntityId(),
                'reference' => $taxScheme->getSchemeRegistrationNumber($storeId),
                'name' => $taxScheme->getSchemeName(),
                'store_currency' => $order->getOrderCurrencyCode(),
                'store_base_currency' => $order->getBaseCurrencyCode(),
                'scheme_currency' => $taxScheme->getSchemeCurrencyCode(),
                'exchange_rate_store_to_store_base' => (float)$storeToBase,
                'exchange_rate_store_base_to_scheme' => (float)$taxScheme->getSchemeExchangeRate($storeId),
                'import_threshold_store_base' => (float)$taxScheme->getThresholdInBaseCurrency($storeId),
                'import_threshold_store' => (float)$taxScheme->getThresholdInBaseCurrency($storeId) /
                    $storeToBase,
                'import_threshold_scheme' => (float)$taxScheme->getThresholdInSchemeCurrency($storeId)
            ];
            $orderTaxScheme = $this->orderTaxSchemeFactory->create();
            $orderTaxScheme->setData($data)->save();
        }
    }
}
