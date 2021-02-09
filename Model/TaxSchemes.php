<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Model\TaxSchemes\AbstractTaxScheme;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Calculation as ResourceCalculation;
use Psr\Log\LoggerInterface;

class TaxSchemes
{
    /**
     * @var AbstractTaxScheme[]
     */
    private $taxSchemes = [];

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;

    /**
     * @var Calculation
     */
    private $calculation;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var ResourceCalculation
     */
    private $resourceCalculation;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param AbstractTaxScheme[] $taxSchemes
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        array $taxSchemes,
        TaxRateRepositoryInterface $taxRateRepository,
        Calculation $calculation,
        GroupRepositoryInterface $groupRepository,
        ResourceCalculation $resourceCalculation,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->taxSchemes = $taxSchemes;
        $this->taxRateRepository = $taxRateRepository;
        $this->calculation = $calculation;
        $this->groupRepository = $groupRepository;
        $this->resourceCalculation = $resourceCalculation;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }

    /**
     * Get available Tax Schemes
     *
     * @return AbstractTaxScheme[]
     */
    public function getTaxSchemes()
    {
        return $this->taxSchemes;
    }

    /**
     * Get list of enabled tax schemes
     *
     * @param int|null $storeId
     * @return AbstractTaxScheme[]
     */
    public function getEnabledTaxSchemes($storeId)
    {
        $enabledSchemes = [];
        foreach ($this->taxSchemes as $taxScheme) {
            if ($taxScheme->isEnabled($storeId)) {
                $enabledSchemes[] = $taxScheme;
            }
        }
        return $enabledSchemes;
    }

    /**
     * return list of Tax Schemes as option array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $array = [];
        foreach ($this->taxSchemes as $taxScheme) {
            $array[] = ['value' => $taxScheme->getSchemeId(), 'label' => $taxScheme->getSchemeName()];
        }
        return $array;
    }

    /**
     * Get the instance of the tax scheme by code
     *
     * @param string $code
     * @return void
     */
    public function getTaxScheme($code)
    {
        return isset($this->taxSchemes[$code]) ? $this->taxSchemes[$code] : null;
    }

    /**
     * Return an instance of the TaxScheme linked to Tax Rate
     *
     * @param int $rateId
     * @return null|TaxRateInterface
     */
    public function getTaxSchemeFromTaxRate($rateId)
    {
        try {
            $taxRate = $this->taxRateRepository->get($rateId);
            $tax_scheme_id = $taxRate->getExtensionAttributes()->getTaxSchemeId();
            if (!$tax_scheme_id) {
                return null;
            }
            return $this->getTaxScheme($tax_scheme_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Retrieve list of Tax Rates that would be applied to order
     * given current tax settings. It effectively reprocesses the order
     * for tax given the current rules. If the rules change, and this is
     * called again, it is possible that you get a different set of Tax IDs
     *
     * @param Order $order
     * @return array
     */
    public function getTaxRateIdsFromOrder($order)
    {
        $request = $this->calculation->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            $this->groupRepository->getById($order->getCustomerGroupId())->getTaxClassId(),
            $order->getStoreId(),
            $order->getCustomerId()
        );

        $productTaxClassIds = [];
        /** @var Magento\Sales\Model\Order\Item $item */
        foreach ($order->getItems() as $item) {
            try {
                /** @var Magento\Catalog\Model\Product $product */
                $product = $this->productRepository->getById($item->getProductId());
                $productTaxClassIds[] = $product->getTaxClassId();
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            } catch (\Exception $e) {
                //The product no longer exists.
            }
        }
        $request->setProductClassId($productTaxClassIds);
        return $this->resourceCalculation->getRateIds($request);
    }
}
