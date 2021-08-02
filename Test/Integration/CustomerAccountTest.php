<?php
declare(strict_types=1);

namespace Gw\AutoCustomerGroup\Test\Integration;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\ClassModel;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Quote\Api\Data\AddressInterfaceFactory as QuoteAddressInterfaceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Test placing an order with existing Customer Account
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CustomerAccountTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

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
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    private $caRepository;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @var ProductInterface
     */
    private $product;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->groupFactory = $this->objectManager->get(GroupInterfaceFactory::class);
        $this->groupRepository = $this->objectManager->get(GroupRepositoryInterface::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $this->caRepository = $this->objectManager->get(AddressRepositoryInterface::class);

        $storeId = $this->storeManager->getStore()->getId();

        $this->createGroup("taxclass1", "group1", "autocustomergroup/ukvat/domestic");
        $this->createGroup("taxclass2", "group2", "autocustomergroup/ukvat/intraeub2b");
        $this->createGroup("taxclass3", "group3", "autocustomergroup/ukvat/intraeub2c");
        $this->createGroup("taxclass4", "group4", "autocustomergroup/ukvat/importb2b");
        $this->createGroup("taxclass5", "group5", "autocustomergroup/ukvat/importtaxed");
        $this->createGroup("taxclass6", "group6", "autocustomergroup/ukvat/importuntaxed");

        /** @var CustomerInterface $customer */
        $this->customer = $this->objectManager->create(CustomerInterfaceFactory::class)->create();
        $this->customer->setWebsiteId(1)
            ->setEmail('test@test.com')
            ->setGroupId(0)
            ->setStoreId($storeId)
            ->setFirstname('First')
            ->setLastname('Last');
        $this->customer = $this->customerRepository->save($this->customer);

        /** @var ProductInterface $product */
        $this->product = $this->objectManager->create(ProductInterfaceFactory::class)->create();
        $this->product->setWebsiteIds([$this->storeManager->getStore($storeId)->getWebsiteId()])
            ->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setName("Simple")
            ->setSku('simple')
            ->setPrice(1)
            ->setTaxClassId(2)
            ->setStoreId($storeId)
            ->setStockData(['use_config_manage_stock' => 0])
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->save();
    }

    public function createGroup($classname, $groupname, $configpath)
    {
        $customerTaxClass = $this->objectManager->create(ClassModel::class)
            ->setClassName($classname)
            ->setClassType(ClassModel::TAX_CLASS_TYPE_CUSTOMER)
            ->save();
        $group = $this->groupFactory->create();
        $group->setCode($groupname)
            ->setTaxClassId($customerTaxClass->getClassId());
        $group = $this->groupRepository->save($group);
        $this->config->setValue($configpath, $group->getId(), ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $startingGroup
     * @param $qty
     * @param $shopCountry
     * @param $buyerCountry
     * @param $postCode
     * @param $taxid
     * @param $expectedGroup
     * @return void
     * @throws InputException
     * @throws InputMismatchException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @magentoConfigFixture current_store autocustomergroup/general/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/general/enable_sales_order_tax_scheme_table 0
     * @magentoConfigFixture current_store tax/classes/shipping_tax_class 2
     * @magentoConfigFixture current_store autocustomergroup/ukvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/ukvat/exchangerate 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 135
     * @magentoConfigFixture current_store general/store_information/postcode 12345
     * @dataProvider dataProviderForTest
     */
    public function testCreateOrder(
        $startingGroup,
        $qty,
        $shopCountry,
        $buyerCountry,
        $postCode,
        $taxid,
        $expectedGroup
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $this->config->setValue('general/store_information/country_id', $shopCountry, ScopeInterface::SCOPE_STORE);

        /** @var AddressInterface $customerAddress */
        $customerAddress = $this->objectManager->create(AddressInterfaceFactory::class)->create();
        $customerAddress->setTelephone("12345")
            ->setPostcode($postCode)
            ->setCountryId($buyerCountry)
            ->setCity("City")
            ->setStreet(["Street1"])
            ->setLastname("Last")
            ->setFirstname("First")
            ->setCustomerId($this->customer->getId())
            ->setIsDefaultBilling(true)
            ->setIsDefaultShipping(true)
            ->setVatId($taxid);

        $customerAddress = $this->caRepository->save($customerAddress);
        if (is_string($startingGroup)) {
            $startingGroup = $this->config->getValue(
                "autocustomergroup/ukvat/" . $startingGroup,
                ScopeInterface::SCOPE_STORE
            );
        }
        $this->customer->setGroupId($startingGroup);
        $this->customer->setAddresses([$customerAddress]);
        $this->customer->setDefaultBilling($customerAddress->getId());
        $this->customer->setDefaultShipping($customerAddress->getId());
        $this->customer = $this->customerRepository->save($this->customer);

        /** @var $quote Quote */
        $quote = $this->objectManager->create(CartInterfaceFactory::class)->create();
        $quote->setCustomer($this->customer);
        $quote->setStoreId($storeId);
        /** @var $quoteItem Item */
        $quoteItem = $quote->addProduct($this->product);
        $quoteItem->setQty($qty);
        $quote->getPayment()->setMethod('checkmo');
        $quoteAddress = $this->objectManager->get(QuoteAddressInterfaceFactory::class)->create();
        $quoteAddress->importCustomerAddressData(
            $this->caRepository->getById($this->customer->getDefaultBilling())
        );
        $quote->setShippingAddress($quoteAddress);
        $quote->setBillingAddress($quoteAddress);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();
        $quoteItem->setQuote($quote);
        $quote->save();
        $quoteItem->save();
        $extAttr = $quote->getExtensionAttributes();
        $groupToSet = $extAttr->getAutocustomergroupNewId();
        if (is_string($expectedGroup)) {
            $expectedGroup = $this->config->getValue(
                "autocustomergroup/ukvat/" . $expectedGroup,
                ScopeInterface::SCOPE_STORE
            );
        }
        $this->assertEquals(
            $expectedGroup,
            $groupToSet
        );
    }

    /**
     * @return array
     */
    public function dataProviderForTest(): array
    {
        //Starting Group
        //Qty to buy
        //ShopCountry
        //BuyerCountry
        //Postcode
        //VatID
        //ExpectedGroup
        return [
            ["domestic", 140, null, "GB", null, null, "domestic"],
            ["domestic", 130, "US", "GB", "SW1 1AA", "", "importtaxed"],
            ["domestic", 140, "US", "GB", "SW1 1AA", "", "importuntaxed"],
            ["domestic", 130, "GB", "GB", "SW1 1AA", "", "domestic"],
            ["domestic", 140, "GB", "GB", "SW1 1AA", "", "domestic"],
            [0, 140, "GB", "GB", "SW1 1AA", "", "domestic"],
            [0, 140, "FR", "FR", "7000", "", 0],
            [0, 130, "GB", "GB", "SW1 1AA", "", "domestic"],
            [0, 130, "FR", "FR", "7000", "", 0],
            ["domestic", 140, "FR", "FR", "7000", "", "domestic"],
            ["importuntaxed", 140, "FR", "FR", "7000", "", "importuntaxed"],
            [0, 140, "FR", "GB", "BT1 1AA", "", "intraeub2c"],
            [0, 140, "FR", "GB", "BT1 1AA", "GB146295999727", "intraeub2b"],
            [0, 130, "FR", "GB", "BT1 1AA", "", "intraeub2c"],
            [0, 130, "FR", "GB", "BT1 1AA", "GB146295999727", "intraeub2b"],
            [0, 140, "FR", "GB", "", "GB146295999727", "importb2b"],
            ["domestic", 140, "GB", "GB", null, "GB146295999727", "domestic"],
            ["domestic", 140, "GB", "GB", null, null, "domestic"],
        ];
    }
}
