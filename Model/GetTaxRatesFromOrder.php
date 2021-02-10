<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Api\GetTaxRatesFromOrderInterface;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation;
use Magento\Tax\Model\ResourceModel\Calculation as ResourceCalculation;

class GetTaxRatesFromOrder implements GetTaxRatesFromOrderInterface
{
    /**
     * @var Calculation
     */
    private $calculation;

    /**
     * @var TaxRateRepositoryInterface
     */
    private $taxRateRepository;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var ResourceCalculation
     */
    private $resourceCalculation;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @param Calculation $calculation
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param GroupRepositoryInterface $groupRepository
     * @param ResourceCalculation $resourceCalculation
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        Calculation $calculation,
        TaxRateRepositoryInterface $taxRateRepository,
        GroupRepositoryInterface $groupRepository,
        ResourceCalculation $resourceCalculation,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->calculation = $calculation;
        $this->taxRateRepository = $taxRateRepository;
        $this->groupRepository = $groupRepository;
        $this->resourceCalculation = $resourceCalculation;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Return an array of Tax Rates used by order.
     *
     * See interface definition for more detailed description
     *
     * @param OrderInterface $order
     * @return TaxRateInterface[]
     */
    public function getRatesByLookup($order)
    {
        $appliedTaxes = $order->getExtensionAttributes()->getAppliedTaxes();
        $taxRates = [];
        foreach ($appliedTaxes as $appliedTax) {
            $taxRate = $this->findTaxRateByCode($appliedTax->getCode());
            if ($taxRate) {
                $taxRates[] = $taxRate;
            }
        }
        return $taxRates;
    }

    /**
     * Return an array of Tax Rates used by order.
     *
     * See interface definition for more detailed description
     *
     * @param OrderInterface $order
     * @return TaxRateInterface[]
     */
    public function getRatesByReProcessing($order)
    {
        /** @var Order $order */
        $request = $this->calculation->getRateRequest(
            $order->getShippingAddress(),
            $order->getBillingAddress(),
            $this->groupRepository->getById($order->getCustomerGroupId())->getTaxClassId(),
            $order->getStoreId(),
            $order->getCustomerId()
        );
        $request->setProductClassId($this->getProductClassIds($order));
        $rateIds = $this->resourceCalculation->getRateIds($request);
        $taxRates = [];
        foreach ($rateIds as $rateId) {
            $taxRates[] = $this->taxRateRepository->get($rateId);
        }
        return $taxRates;
    }

    /**
     * Return array of Product Class Tax Ids from order
     *
     * @param OrderInterface $order
     * @return int[]
     */
    private function getProductClassIds($order)
    {
        $productTaxClassIds = [];
        /** @var Item $item */
        foreach ($order->getItems() as $item) {
            try {
                /** @var Product $product */
                $product = $this->productRepository->getById($item->getProductId());
                $productTaxClassIds[] = $product->getTaxClassId();
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
            } catch (\Exception $e) {
                //The product no longer exists.
            }
        }
        return $productTaxClassIds;
    }

    /**
     * Lookup the Tax Rate, given a code
     *
     * @param string $code
     * @return void
     */
    private function findTaxRateByCode($code)
    {
        $filter = $this->filterBuilder
            ->setField('code')
            ->setConditionType('eq')
            ->setValue($code)
            ->create();
        $this->searchCriteriaBuilder->addFilters([$filter]);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $taxRates = $this->taxRateRepository->getList($searchCriteria);
        if ($taxRates->getTotalCount() == 1) {
            //Only return if there is an exact match, and only one match
            return $taxRates->getItems()[0];
        } else {
            return null;
        }
    }
}
