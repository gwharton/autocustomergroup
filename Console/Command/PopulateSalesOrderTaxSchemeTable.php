<?php
namespace Gw\AutoCustomerGroup\Console\Command;

use Gw\AutoCustomerGroup\Api\Data\OrderTaxSchemeInterfaceFactory;
use Gw\AutoCustomerGroup\Model\TaxSchemes;
use Magento\Sales\Model\OrderRepository;
use Magento\Tax\Model\Sales\Order\Tax;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Magento\Framework\Console\Cli;
use Magento\Tax\Model\ResourceModel\Sales\Order\Tax\CollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;
use Exception;

class PopulateSalesOrderTaxSchemeTable extends Command
{
    /** Command name */
    const NAME = 'autocustomergroup:sales-order-tax:populate';

    /**
     * @var CollectionFactory
     */
    private $orderTaxCollectionFactory;

    /**
     * @var OrderTaxSchemeInterfaceFactory
     */
    private $otsFactory;

    /**
     * @var TaxSchemes
     */
    private $taxSchemes;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var State
     */
    private $state;

    /**
     * @param CollectionFactory $orderTaxCollectionFactory
     * @param OrderTaxSchemeInterfaceFactory $otsFactory
     * @param OrderRepository $orderRepository
     * @param State $state
     * @param TaxSchemes $taxSchemes
     */
    public function __construct(
        CollectionFactory $orderTaxCollectionFactory,
        OrderTaxSchemeInterfaceFactory $otsFactory,
        OrderRepository $orderRepository,
        State $state,
        TaxSchemes $taxSchemes
    ) {
        $this->orderTaxCollectionFactory = $orderTaxCollectionFactory;
        $this->otsFactory = $otsFactory;
        $this->orderRepository = $orderRepository;
        $this->state = $state;
        parent::__construct();
        $this->taxSchemes = $taxSchemes;
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
                $taxScheme = $this->taxSchemes->getTaxScheme('ukvat');
                $order = $this->orderRepository->get($tax->getOrderId());
                $storeId = $order->getStoreId();
                $baseToStore = 1 / ($order->getStoreToBaseRate() == 0.0 ? 1.0 : $order->getStoreToBaseRate());
                $orderTaxScheme = $this->otsFactory->create();
                $orderTaxScheme->setOrderId((int)$tax->getOrderId());
                $orderTaxScheme->setReference($taxScheme->getSchemeRegistrationNumber($storeId));
                $orderTaxScheme->setName($taxScheme->getSchemeName());
                $orderTaxScheme->setStoreCurrency($order->getOrderCurrencyCode());
                $orderTaxScheme->setBaseCurrency($order->getBaseCurrencyCode());
                $orderTaxScheme->setSchemeCurrency($taxScheme->getSchemeCurrencyCode());
                $orderTaxScheme->setExchangeRateBaseToStore((float)$baseToStore);
                $orderTaxScheme->setExchangeRateSchemeToBase((float)$taxScheme->getSchemeExchangeRate($storeId));
                $orderTaxScheme->setImportThresholdBase((float)$taxScheme->getThresholdInBaseCurrency($storeId));
                $orderTaxScheme->setImportThresholdStore((float)$taxScheme->getThresholdInBaseCurrency($storeId) *
                    $baseToStore);
                $orderTaxScheme->setImportThresholdScheme((float)$taxScheme->getThresholdInSchemeCurrency($storeId));
                $orderTaxScheme->save();
                $output->writeln('<info>Saving Tax Scheme</info>');
            }

        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
    }
}
