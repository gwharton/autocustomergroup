<?php
namespace Gw\AutoCustomerGroup\Console\Command;

use Gw\AutoCustomerGroup\Model\OrderTaxSchemeFactory;
use Gw\AutoCustomerGroup\Api\Data\TaxSchemeInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\OrderRepository;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Model\Sales\Order\Tax;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\Console\Cli;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class PopulateSalesOrderTaxSchemeTable extends Command
{
    /** Command name */
    const NAME = 'autocustomergroup:sales-order-tax:populate';

    /**
     * @var CollectionFactory
     */
    private $orderTaxCollectionFactory;

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
     * @var State
     */
    private $state;

    /**
     * @param CollectionFactory $orderTaxCollectionFactory
     * @param OrderTaxSchemeFactory $orderTaxSchemeFactory
     * @param OrderRepository $orderRepository
     * @param FilterBuilder $filterBuilder
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TaxRateRepositoryInterface $taxRateRepository
     * @param State $state
     */
    public function __construct(
        CollectionFactory $orderTaxCollectionFactory,
        OrderTaxSchemeFactory $orderTaxSchemeFactory,
        OrderRepository $orderRepository,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxRateRepositoryInterface $taxRateRepository,
        State $state
    ) {
        $this->orderTaxCollectionFactory = $orderTaxCollectionFactory;
        $this->orderTaxSchemeFactory = $orderTaxSchemeFactory;
        $this->orderRepository = $orderRepository;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxRateRepository = $taxRateRepository;
        $this->state = $state;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription(
                'Populates sales_order_tax_scheme table from sales_order_tax table.'
            );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_FRONTEND);
        try {
            foreach ($this->orderTaxCollectionFactory->create() as $tax) {
                /** @var Tax $tax */
                $output->writeln('<info>Processing Tax ID ' . $tax->getId() . '</info>');

                $order = $this->orderRepository->get($tax->getOrderId());
                if (!$order) {
                    throw new LocalizedException(__('Failed to Load Order.'));
                }
                $storeId = $order->getStoreId();
                $filter = $this->filterBuilder
                    ->setField('code')
                    ->setConditionType('eq')
                    ->setValue($tax->getCode())
                    ->create();
                $this->searchCriteriaBuilder->addFilters([$filter]);
                $searchCriteria = $this->searchCriteriaBuilder->create();
                $taxRates = $this->taxRateRepository->getList($searchCriteria);
                if ($taxRates->getTotalCount() != 1) {
                    throw new LocalizedException(__('Unable to locate Tax Rate from Tax Code.' . $tax->getCode()));
                }
                $rate = $taxRates->getItems()[0];
                if (!$rate) {
                    throw new LocalizedException(__('Invalid Tax Rate.'));
                }
                /** @var TaxSchemeInterface $taxScheme */
                $taxScheme = $rate->getExtensionAttributes()->getTaxScheme();
                if ($taxScheme) {
                    $percent = $tax->getPercent();
                    $storeToBase = $order->getStoreToBaseRate() == 0.0 ? 1.0 : $order->getStoreToBaseRate();
                    $data = [
                        'tax_id' => (int)$tax->getId(),
                        'order_id' => (int)$tax->getOrderId(),
                        'reference' => $taxScheme->getSchemeRegistrationNumber($storeId),
                        'name' => $taxScheme->getSchemeName(),
                        'code' => $tax->getCode(),
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
                        'taxable_amount_store_base' => (float)$tax->getBaseAmount() / ($percent / 100.0),
                        'taxable_amount_store' => (float)$tax->getAmount() / ($percent / 100.0),
                        'taxable_amount_scheme' => (float)($tax->getBaseAmount() /
                                $taxScheme->getSchemeExchangeRate($storeId)) / ($percent / 100.0),
                        'tax_amount_store_base' => (float)$tax->getBaseAmount(),
                        'tax_amount_store' => (float)$tax->getAmount(),
                        'tax_amount_scheme' => (float)$tax->getBaseAmount() /
                            $taxScheme->getSchemeExchangeRate($storeId)
                    ];

                    $orderTaxScheme = $this->orderTaxSchemeFactory->create();
                    $orderTaxScheme->setData($data)->save();
                    $output->writeln('<info>Saving Tax Scheme</info>');
                }
            }

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }








}
