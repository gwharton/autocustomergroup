<?php
namespace Gw\AutoCustomerGroup\Api;

/**
 * Routines that can be used to obtain the tax rates that were used by the Order
 * There are two variants to choose from depending on how static the tax rates are
 * in the store, and whether you want the tax rates to be based on the current setup
 * or the setup at the time of the order. Both schemes have their drawbacks. See
 * below.
 */
interface GetTaxRatesFromOrderInterface
{
    /**
     * Return an array of Tax Rates used by order. The tax rates are obtained by
     * obtaining the list of tax rate codes from the order's appliedTaxes data
     * and searching for current Tax Rates that match the order's tax rate codes.
     *
     * Note, if the Tax Rate Code has been changed since the order was placed, then
     * this function will not find it.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Tax\Api\Data\TaxRateInterface[]
     */
    public function getRatesByLookup($order);

    /**
     * Return an array of Tax Rates used by order. The tax rates are obtained by
     * re-running the tax calculation using the order details. i.e Shipping and
     * Billing Address, Products, Customer Tax Class, Store and Customer Details
     *
     * Note, if the Tax Rates and rules have changed since the order was placed,
     * this function may return a different set of tax rates than the original
     * order
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Magento\Tax\Api\Data\TaxRateInterface[]
     */
    public function getRatesByReProcessing($order);
}
