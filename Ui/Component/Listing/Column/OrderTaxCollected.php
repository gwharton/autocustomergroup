<?php
namespace Gw\AutoCustomerGroup\Ui\Component\Listing\Column;

use Gw\AutoCustomerGroup\Api\Data\OrderTaxCollectedInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * SOG Column to display Tax Collected information for items ordered.
 */
class OrderTaxCollected extends Column
{
    /**
     * @var OrderTaxCollectedInterface[]
     */
    private $taxCollectors;

    /**
     * ProductsOrdered constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderTaxCollectedInterface[] $taxCollectors
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $taxCollectors,
        array $components = [],
        array $data = []
    ) {
        $this->taxCollectors = $taxCollectors;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item['tax_collected'] = "No";
                foreach ($this->taxCollectors as $taxCollector) {
                    foreach ($taxCollector->getTaxCollectedDetails($item['entity_id']) as $detail) {
                        $item['tax_collected'] = "<div>" . implode(" - ", array_filter($detail)) . '</div>';
                    }
                }
            }
        }
        return $dataSource;
    }
}
