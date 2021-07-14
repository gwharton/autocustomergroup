<?php
declare(strict_types=1);

namespace Gw\AutoCustomerGroup\Test\Integration;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Constraint\StringContains;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Gw\AutoCustomerGroup\Model\ResourceModel\OrderTaxScheme\CollectionFactory;

/**
 * Create test order and examine results in sales_order_tax_scheme table
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class CreateOrderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * GroupInterfaceFactory
     */
    private $groupFactory;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var CollectionFactory
     */
    private $orderTaxSchemeCollectionFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
        $this->addressFactory = $this->objectManager->get(AddressFactory::class);
        $this->guestCartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->guestCartRepository = $this->objectManager->get(GuestCartRepositoryInterface::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->orderRepository = $this->objectManager->get(OrderRepository::class);
        $this->groupFactory = $this->objectManager->get(GroupInterfaceFactory::class);
        $this->groupRepository = $this->objectManager->get(GroupRepositoryInterface::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->orderTaxSchemeCollectionFactory = $this->objectManager->get(CollectionFactory::class);
    }

    /**
     * @return void
     * @magentoConfigFixture current_store autocustomergroup/general/enabled 1
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store autocustomergroup/ukvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/name UK VAT Scheme
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/ukvat/exchangerate 0.5
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 10000
     * @magentoConfigFixture current_store general/store_information/country_id US
     * @magentoConfigFixture current_store general/store_information/postcode 12345
     * @dataProvider dataProviderForTest
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testCreateOrder(
        $algorithm,
        $grandtotal,
        $totalTaxStore,
        $totalTaxStoreBase,
        $totalTaxScheme,
        $totalTaxableStore,
        $totalTaxableStoreBase,
        $totalTaxableScheme
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setValue('tax/calculation/algorithm', $algorithm, ScopeInterface::SCOPE_STORE);
        $product1 = $this->productFactory->create();
        $product1->setTypeId('simple')
            ->setId(1)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 1')
            ->setSku('simple1')
            ->setPrice(123.52)
            ->setTaxClassId(2)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setUrlKey('simple1')
            ->save();
        $product2 = $this->productFactory->create();
        $product2->setTypeId('simple')
            ->setId(2)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 2')
            ->setSku('simple2')
            ->setPrice(145.97)
            ->setTaxClassId(2)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setUrlKey('simple2')
            ->save();
        $addressData = [
            'telephone' => 12345,
            'postcode' => 'SW1 1AA',
            'country_id' => 'GB',
            'city' => 'City',
            'street' => ['Street'],
            'lastname' => 'Lastname',
            'firstname' => 'Firstname',
            'address_type' => 'shipping',
            'email' => 'some_email@mail.com'
        ];

        $groupDataObject = $this->groupFactory->create();
        $groupDataObject->setCode('uk_domestic')->setTaxClassId(3);
        $groupId = $this->groupRepository->save($groupDataObject)->getId();
        $this->config->setValue('autocustomergroup/ukvat/uk_import_taxed', $groupId, ScopeInterface::SCOPE_STORE);

        $taxRate = [
            'tax_country_id' => 'GB',
            'tax_region_id' => '0',
            'tax_postcode' => '*',
            'code' => 'UK VAT',
            'rate' => '20.0000',
            'tax_scheme_id' => 'ukvat'
        ];
        $rate = $this->objectManager->create(\Magento\Tax\Model\Calculation\Rate::class)->setData($taxRate)->save();

        $ruleData = [
            'code' => 'UK VAT Rule',
            'priority' => '0',
            'position' => '0',
            'customer_tax_class_ids' => [3],
            'product_tax_class_ids' => [2],
            'tax_rate_ids' => [$rate->getId()],
            'tax_rates_codes' => [$rate->getId() => $rate->getCode()],
        ];
        $this->objectManager->create(\Magento\Tax\Model\Calculation\Rule::class)->setData($ruleData)->save();

        $shippingAddress = $this->addressFactory->create(['data' => $addressData]);
        $shippingAddress->setAddressType('shipping');
        $billingAddress = $this->addressFactory->create(['data' => $addressData]);
        $billingAddress->setAddressType('billing');

        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        /** @var Quote $quote */
        $quote = $this->guestCartRepository->get($maskedCartId);

        //$quote = $this->quoteFactory->create();
        $quote->setCustomerIsGuest(true)
            ->setStoreId($storeId)
            ->setReservedOrderId('guest_quote');

        $quote->addProduct($this->productRepository->get('simple1'), 3);
        $quote->addProduct($this->productRepository->get('simple2'), 3);
        $quote->setBillingAddress($billingAddress);
        $quote->setShippingAddress($shippingAddress);
        $quote->getPayment()->setMethod('checkmo');
        $quote->getShippingAddress()->setShippingMethod('flatrate_flatrate')->setCollectShippingRates(true);
        $quote->collectTotals();

        $this->quoteRepository->save($quote);

        $checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $checkoutSession->setQuoteId($quote->getId());

        $orderId = $this->guestCartManagement->placeOrder($maskedCartId);
        $order = $this->orderRepository->get($orderId);
        $this->assertNotNull($order->getEntityId());

        $this->assertEquals($grandtotal, $order->getGrandTotal());
        $this->assertEquals($totalTaxStore, $order->getTaxAmount());
        $this->assertEquals($totalTaxStoreBase, $order->getBaseTaxAmount());

        $orderTaxSchemes = $this->orderTaxSchemeCollectionFactory->create()->loadByOrder($order);
        $orderTaxScheme = $orderTaxSchemes->getFirstItem();
        $this->assertNotNull($orderTaxScheme);

        $this->assertEquals($order->getEntityId(), $orderTaxScheme->getOrderId());
        $this->assertEquals("GB553557881", $orderTaxScheme->getReference());
        $this->assertEquals("UK VAT Scheme", $orderTaxScheme->getName());
        $this->assertEquals($rate->getCode(), $orderTaxScheme->getCode());
        $this->assertEquals($rate->getRate(), $orderTaxScheme->getRate());
        $this->assertEquals("USD", $orderTaxScheme->getStoreCurrency());
        $this->assertEquals("USD", $orderTaxScheme->getStoreBaseCurrency());
        $this->assertEquals("GBP", $orderTaxScheme->getSchemeCurrency());
        $this->assertEquals(1.0, (float)$orderTaxScheme->getExchangeRateStoreToStoreBase());
        $this->assertEquals(0.5, (float)$orderTaxScheme->getExchangeRateStoreBaseToScheme());
        $this->assertEquals(5000.0, (float)$orderTaxScheme->getImportThresholdStore());
        $this->assertEquals(5000.0, (float)$orderTaxScheme->getImportThresholdStoreBase());
        $this->assertEquals(10000.0, (float)$orderTaxScheme->getImportThresholdScheme());
        $this->assertEquals($totalTaxableStore, (float)$orderTaxScheme->getTaxableAmountStore());
        $this->assertEquals($totalTaxableStoreBase, (float)$orderTaxScheme->getTaxableAmountStoreBase());
        $this->assertEquals($totalTaxableScheme, (float)$orderTaxScheme->getTaxableAmountScheme());
        $this->assertEquals($totalTaxStore, (float)$orderTaxScheme->getTaxAmountStore());
        $this->assertEquals($totalTaxStoreBase, (float)$orderTaxScheme->getTaxAmountStoreBase());
        $this->assertEquals($totalTaxScheme, (float)$orderTaxScheme->getTaxAmountScheme());
    }

    /**
     * @return array
     */
    public function dataProviderForTest()
    {
        //Tax Calc Method
        //Grand Total
        //Total Tax Store
        //Total Tax Store Base
        //Total Tax Scheme
        return [
            ['UNIT_BASE_CALCULATION', 1006.14, 167.67, 167.67, 335.34, 838.47, 838.47, 1676.94],
            ['ROW_BASE_CALCULATION', 1006.16, 167.69, 167.69, 335.38, 838.47, 838.47, 1676.94],
            ['TOTAL_BASE_CALCULATION', 1006.16, 167.69, 167.69, 335.38, 838.47, 838.47, 1676.94]
        ];
    }
}
