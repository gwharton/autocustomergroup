<?php
declare(strict_types=1);

namespace Gw\AutoCustomerGroup\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\State\InvalidTransitionException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AutoGroupTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var GroupRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var ReinitableConfigInterface
     */
    private $config;

    /**
     * @var GuestCartManagementInterface
     */
    private $guestCartManagement;

    /**
     * @var GuestCartRepositoryInterface
     */
    private $guestCartRepository;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * GroupInterfaceFactory
     */
    private $groupFactory;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @var AddressFactory
     */
    private $addressFactory;

    /**
     * @var RuleFactory
     */
    private $ruleFactory;

    /**
     * @var ProductFactory
     */
    private $productFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->groupRepository = $this->objectManager->get(GroupRepositoryInterface::class);
        $this->config = $this->objectManager->get(ReinitableConfigInterface::class);
        $this->guestCartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $this->guestCartRepository = $this->objectManager->get(GuestCartRepositoryInterface::class);
        $this->orderRepository = $this->objectManager->get(OrderRepository::class);
        $this->quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->groupFactory = $this->objectManager->get(GroupInterfaceFactory::class);
        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->ruleRepository = $this->objectManager->get(RuleRepository::class);
        $this->ruleFactory = $this->objectManager->get(RuleFactory::class);
        $this->addressFactory = $this->objectManager->get(AddressFactory::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
    }

    /**
     * @param int $qty
     * @param float $price
     * @param string $merchantCountry
     * @param string|null $merchantPostCode
     * @param string $destinationCountry
     * @param string|null $destinationPostcode
     * @param string|null $destinationVatId
     * @param string $customerGroup
     * @param float $percentageDiscount
     *
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws InvalidTransitionException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @dataProvider dataProviderForTestAutoCustomerGroup
     * @magentoConfigFixture current_store autocustomergroup/general/enabled 1
     * @magentoConfigFixture current_store currency/options/default GBP
     * @magentoConfigFixture current_store currency/options/base GBP
     * @magentoConfigFixture current_store autocustomergroup/ukvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/ukvat/exchangerate 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 135
     * @magentoConfigFixture current_store autocustomergroup/euvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationcountry IE
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationnumber 100
     * @magentoConfigFixture current_store autocustomergroup/euvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/euvat/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/euvat/exchangerate 0.88603
     * @magentoConfigFixture current_store autocustomergroup/euvat/importthreshold 150
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/registrationnumber 12345
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/exchangerate 0.08540
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importthreshold 3000
     * @magentoConfigFixture current_store autocustomergroup/australiagst/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/australiagst/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/australiagst/exchangerate 0.5666
     * @magentoConfigFixture current_store autocustomergroup/australiagst/importthreshold 1000
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/usemagentoexchangerate 0
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/exchangerate 0.52
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importthreshold 1000
     * @magentoConfigFixture current_store general/region/state_required ""
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testAutoCustomerGroup(
        int $qty,
        float $price,
        string $merchantCountry,
        ?string $merchantPostCode,
        string $destinationCountry,
        ?string $destinationPostcode,
        ?string $destinationVatId,
        string $customerGroup,
        float $percentageDiscount,
        bool $onlineTest = false
    ): void {
        $storeId = $this->storeManager->getStore()->getId();

        $this->config->setValue(
            "autocustomergroup/newzealandgst/validate_online",
            $onlineTest,
            ScopeInterface::SCOPE_STORE
        );

        $groups = [0];
        $groups[] = $this->createGroupAndAssign('uk_domestic', 'autocustomergroup/ukvat/domestic');
        $groups[] = $this->createGroupAndAssign('uk_intraeu_b2b', 'autocustomergroup/ukvat/intraeub2b');
        $groups[] = $this->createGroupAndAssign('uk_intraeu_b2c', 'autocustomergroup/ukvat/intraeub2c');
        $groups[] = $this->createGroupAndAssign('uk_import_b2b', 'autocustomergroup/ukvat/importb2b');
        $groups[] = $this->createGroupAndAssign('uk_import_taxed', 'autocustomergroup/ukvat/importtaxed');
        $groups[] = $this->createGroupAndAssign('uk_import_untaxed', 'autocustomergroup/ukvat/importuntaxed');

        $groups[] = $this->createGroupAndAssign('eu_domestic', 'autocustomergroup/euvat/domestic');
        $groups[] = $this->createGroupAndAssign('eu_intraeu_b2b', 'autocustomergroup/euvat/intraeub2b');
        $groups[] = $this->createGroupAndAssign('eu_intraeu_b2c', 'autocustomergroup/euvat/intraeub2c');
        $groups[] = $this->createGroupAndAssign('eu_import_b2b', 'autocustomergroup/euvat/importb2b');
        $groups[] = $this->createGroupAndAssign('eu_import_taxed', 'autocustomergroup/euvat/importtaxed');
        $groups[] = $this->createGroupAndAssign('eu_import_untaxed', 'autocustomergroup/euvat/importuntaxed');

        $groups[] = $this->createGroupAndAssign('norway_domestic', 'autocustomergroup/norwayvoec/domestic');
        $groups[] = $this->createGroupAndAssign('norway_import_b2b', 'autocustomergroup/norwayvoec/importb2b');
        $groups[] = $this->createGroupAndAssign('norway_import_taxed', 'autocustomergroup/norwayvoec/importtaxed');
        $groups[] = $this->createGroupAndAssign('norway_import_untaxed', 'autocustomergroup/norwayvoec/importuntaxed');

        $groups[] = $this->createGroupAndAssign('australia_domestic', 'autocustomergroup/australiagst/domestic');
        $groups[] = $this->createGroupAndAssign('australia_import_b2b', 'autocustomergroup/australiagst/importb2b');
        $groups[] = $this->createGroupAndAssign('australia_import_taxed', 'autocustomergroup/australiagst/importtaxed');
        $groups[] = $this->createGroupAndAssign(
            'australia_import_untaxed',
            'autocustomergroup/australiagst/importuntaxed'
        );

        $groups[] = $this->createGroupAndAssign('newzealand_domestic', 'autocustomergroup/newzealandgst/domestic');
        $groups[] = $this->createGroupAndAssign('newzealand_import_b2b', 'autocustomergroup/newzealandgst/importb2b');
        $groups[] = $this->createGroupAndAssign(
            'newzealand_import_taxed',
            'autocustomergroup/newzealandgst/importtaxed'
        );
        $groups[] = $this->createGroupAndAssign(
            'newzealand_import_untaxed',
            'autocustomergroup/newzealandgst/importuntaxed'
        );

        if ($percentageDiscount > 0) {
            $this->createSalesRule($groups, $percentageDiscount, $storeId);
        }

        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            $merchantCountry,
            ScopeInterface::SCOPE_STORE
        );
        $this->config->setValue(
            StoreInformation::XML_PATH_STORE_INFO_POSTCODE,
            $merchantPostCode,
            ScopeInterface::SCOPE_STORE
        );

        $product = $this->productFactory->create();
        $product->setTypeId('simple')
            ->setId(1)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product')
            ->setSku('simple')
            ->setPrice($price)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 0])
            ->save();

        $addressData = [
            'telephone' => 12345,
            'postcode' => $destinationPostcode,
            'country_id' => $destinationCountry,
            'city' => 'City',
            'street' => ['Street'],
            'lastname' => 'Lastname',
            'firstname' => 'Firstname',
            'address_type' => 'shipping',
            'email' => 'some_email@mail.com'
        ];

        $shippingAddress = $this->addressFactory->create(['data' => $addressData]);
        $shippingAddress->setAddressType('shipping');
        $shippingAddress->setVatId($destinationVatId);
        $billingAddress = $this->addressFactory->create(['data' => $addressData]);
        $billingAddress->setAddressType('billing');

        $maskedCartId = $this->guestCartManagement->createEmptyCart();
        /** @var Quote $quote */
        $quote = $this->guestCartRepository->get($maskedCartId);

        //$quote = $this->quoteFactory->create();
        $quote->setCustomerIsGuest(true)
            ->setStoreId($storeId)
            ->setReservedOrderId('guest_quote');

        $quote->addProduct($this->productRepository->get('simple'), $qty);
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

        $group = $this->groupRepository->getById($order->getCustomerGroupId());
        $this->assertEquals($customerGroup, $group->getCode());
    }

    /**
     * @param string $code
     * @param string $assign
     * @return int
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InvalidTransitionException
     */
    public function createGroupAndAssign(string $code, string $assign): int
    {
        $groupDataObject = $this->groupFactory->create();
        $groupDataObject->setCode($code)->setTaxClassId(3);
        $groupId = $this->groupRepository->save($groupDataObject)->getId();
        $this->config->setValue($assign, $groupId, ScopeInterface::SCOPE_STORE);
        return (int)$groupId;
    }

    public function createSalesRule($groupIds, $percentage, $storeId)
    {
        $allRules = $this->ruleRepository->getList(
            $this->objectManager->get(SearchCriteriaInterface::class)
        );
        foreach ($allRules->getItems() as $rule) {
            $this->ruleRepository->deleteById($rule->getRuleId());
        }

        $ruleData =  [
            'name' => 'Discount',
            'is_active' => 1,
            'customer_group_ids' => $groupIds,
            'coupon_type' => Rule::COUPON_TYPE_NO_COUPON,
            'simple_action' => 'by_percent',
            'discount_amount' => $percentage,
            'discount_step' => 0,
            'stop_rules_processing' => 1,
            'website_ids' => [$storeId]
        ];
        $salesRule = $this->ruleFactory->create(['data' => $ruleData]);
        $salesRule->save();
    }

    /**
     * @return array
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function dataProviderForTestAutoCustomerGroup(): array
    {
        //Item Qty
        //Item Price
        //Merchant Country
        //Merchant Postcode
        //Destination Country
        //Destination Postcode
        //Tax ID
        //Expected Customer Group
        //Discount Percentage
        //Online Test
        return [
            //New Zealand GST
            //Threshold is 1000NZD = 520.00GBP
            [1, 10, 'NZ', null, 'NZ', "0620", '', 'newzealand_domestic', 0],

            //Online and offline should both show invalid
            [1, 10, 'NZ', null, 'NZ', "0620", '1234', 'newzealand_domestic', 0, true],
            [1, 10, 'NZ', null, 'NZ', "0620", '1234', 'newzealand_domestic', 0],

            //Online check will show valid but no GST, Offline check will show valid
            [1, 10, 'NZ', null, 'NZ', "0620", '9429038644047', 'newzealand_domestic', 0, true],
            [1, 10, 'NZ', null, 'NZ', "0620", '9429038644047', 'newzealand_domestic', 0],

            //Online check will show valid with GST, Offline check will show valid
            [1, 10, 'GB', 'NE1 1AA', 'NZ', "0620", '9429050853731', 'newzealand_import_b2b', 0, true],
            [1, 10, 'GB', 'NE1 1AA', 'NZ', "0620", '9429050853731', 'newzealand_import_b2b', 0],

            //Online check will show valid with GST, Offline check will show valid
            [10, 1000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429050853731', 'newzealand_import_b2b', 0, true],
            [10, 1000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429050853731', 'newzealand_import_b2b', 0],

            //Online check will show valid with GST, Offline check will show valid
            [1, 4000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429049835892', 'newzealand_import_b2b', 0, true],
            [1, 4000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429049835892', 'newzealand_import_b2b', 0],

            //Online check will show valid but no GST, Offline check will show valid
            [10, 1000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429038644047', 'newzealand_import_untaxed', 0, true],
            [10, 1000, 'GB', 'NE1 1AA', 'NZ', "0620", '9429038644047', 'newzealand_import_b2b', 0],

            //Online and offline should both show invalid
            [1, 10, 'GB', 'NE1 1AA', 'NZ', "0620", '1234', 'newzealand_import_taxed', 0, true],
            [1, 10, 'GB', 'NE1 1AA', 'NZ', "0620", '1234', 'newzealand_import_taxed', 0],

            [1, 10, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_taxed', 0],
            [5, 100, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_taxed', 0],
            [5, 500, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_taxed', 0],
            [1, 520, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_taxed', 0],
            [1, 530, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_untaxed', 0],
            [1, 2000, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_untaxed', 0],
            [5, 600, 'GB', 'NE1 1AA', 'NZ', "0620", '', 'newzealand_import_untaxed', 0],

            //Online and offline should both show invalid
            [5, 4000, 'GB', 'NE1 1AA', 'NZ', "0620", '1234', 'newzealand_import_untaxed', 0, true],
            [5, 4000, 'GB', 'NE1 1AA', 'NZ', "0620", '1234', 'newzealand_import_untaxed', 0],

            //USA
            [1, 10, 'GB', 'NE1 1AA', 'US', '90210', '', 'NOT LOGGED IN', 0 ],

            //Brazil
            [1, 10, 'FR', null, 'BR', '73700-000', '', 'NOT LOGGED IN', 0],
            [1, 10, 'GB', 'NE1 1AA', 'BR', '73700-000', '', 'NOT LOGGED IN', 0],

            //UK VAT
            //Threshold is 135GBP
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'NE1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', 'BT1 1AA', 'GB', 'NE1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'BT1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'NE1 1AA', 'GB948561936944', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'IM', 'NE1 1AA', 'GB', 'NE1 1AA', 'GB948561936944', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'GB', 'NE1 1AA', 'IM', 'IM1 1AA', 'GB000549615108', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'IM', 'IM1 1AA', 'IM', 'IM1 1AA', 'GB000549615108', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'NE1 1AA', 'GB123', 'uk_domestic', 0], //VAT is invalid
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'NE1 1AA', 'GB948561936943', 'uk_domestic', 0], //VAT is invalid
            [1, 10, 'FR', null, 'GB', 'BT1 1AA', 'GB948561936944', 'uk_intraeu_b2b', 0], //VAT is invalid
            [1, 10, 'FR', null, 'GB', 'BT1 1AA', '', 'uk_intraeu_b2c', 0],
            [1, 10, 'FR', null,  'GB', 'NE1 1AA', 'GB948561936944', 'uk_import_b2b', 0], //VAT is valid
            [10, 10, 'FR', null, 'GB', 'NE1 1AA', 'GB948561936944', 'uk_import_b2b', 0], //VAT is valid
            [1, 10, 'FR', null, 'GB', 'NE1 1AA', '', 'uk_import_taxed', 0],
            [20, 10, 'FR', null, 'GB', 'NE1 1AA', '', 'uk_import_taxed', 50], //20 x 10ea = 200 * 50% = 100
            [1, 130, 'FR', null, 'GB', 'NE1 1AA', '', 'uk_import_taxed', 0],

            [14, 10, 'FR', null, 'GB', 'NE1 1AA', '', 'uk_import_untaxed', 0],
            [30, 10, 'FR', null, 'GB', 'NE1 1AA', '', 'uk_import_untaxed', 50], //30 x 10ea = 300 * 50% = 200

            //EU VAT
            //Threshold is 150EUR = 132.90 GBP
            [1, 10, 'IE', null, 'IE', null, '', 'eu_domestic', 0],
            [1, 10, 'IE', null, 'IE', null, '100', 'eu_domestic', 0],
            [1, 10, 'DE', null, 'IE', null, '100', 'eu_intraeu_b2b', 0],
            [1, 10, 'GB', 'BT1 1AA', 'IE', null, '100', 'eu_intraeu_b2b', 0],
            [1, 10, 'DE', null, 'IE', null, '200', 'eu_intraeu_b2c', 0], //VAT is invalid
            [1, 10, 'GB', 'BT1 1AA', 'IE', null, '', 'eu_intraeu_b2c', 0],
            [1, 10, 'GB', 'NE1 1AA', 'IE', null, '100', 'eu_import_b2b', 0],
            //30 x 10ea = 300,  * 50% = 150
            [30, 10, 'GB', 'NE1 1AA', 'IE', null, '100', 'eu_import_b2b', 50],
            //18 x 10ea = 180,  * 50% = 90
            [18, 10, 'GB', 'NE1 1AA', 'IE', null, '100', 'eu_import_b2b', 50],
            //16 x 10ea = 160,  * 50% = 80
            [16, 10, 'GB', 'NE1 1AA', 'IE', null,'100', 'eu_import_b2b', 50],
            [1, 10, 'BR', null, 'IE', null, '100', 'eu_import_b2b', 0],
            [1, 10, 'GB', 'NE1 1AA', 'FR', '75001', '', 'eu_import_taxed', 0],
            [1, 10, 'GB', 'NE1 1AA', 'IE', null, '123456', 'eu_import_taxed', 0],
            [1, 10, 'BR', null, 'IE', null, '', 'eu_import_taxed', 0],
            [1, 130, 'BR', null, 'IE', null, '', 'eu_import_taxed', 0],
            [1, 140, 'BR', null, 'IE', null, '', 'eu_import_untaxed', 0],
            [14, 10, 'GB', 'NE1 1AA', 'FR', '75001', '', 'eu_import_untaxed', 0],
            [28, 10, 'GB', 'NE1 1AA', 'FR', '75001', '', 'eu_import_untaxed', 50], //28 x 10ea = 280 * 50% = 140

            //Norway VOEC
            //Threshold is 3000NOK = 256.20GBP
            [1, 10, 'NO', '1234', 'NO', '1366', '', 'norway_domestic', 0],
            [1, 10, 'NO', '1234', 'NO', '1366', '912345678', 'norway_domestic', 0], //Valid Business No
            [1, 10, 'NO', '1234', 'NO', '1366', '2443', 'norway_domestic', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1366', '912345678', 'norway_import_b2b', 0], //Valid Business No
            [10, 20, 'GB', 'NE1 1AA', 'NO', '1366', '912345678', 'norway_import_b2b', 0], //Valid Business No
            [1, 300, 'GB', 'NE1 1AA', 'NO', '1366', '812345678', 'norway_import_b2b', 0], //Valid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1366', '2443', 'norway_import_taxed', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1366', '', 'norway_import_taxed', 0],
            [10, 200, 'GB', 'NE1 1AA', 'NO', '1366', '', 'norway_import_taxed', 0],
            [1, 250, 'GB', 'NE1 1AA', 'NO', '1366', '', 'norway_import_taxed', 0],
            [1, 260, 'GB', 'NE1 1AA', 'NO', '1366', '', 'norway_import_untaxed', 0],
            [5, 300, 'GB', 'NE1 1AA', 'NO', '1366', '', 'norway_import_untaxed', 0],
            [1, 300, 'GB', 'NE1 1AA', 'NO', '1366', '2443', 'norway_import_untaxed', 0], //Invalid Business No

            //Australia GST
            //Threshold is 1000AUD = 566.60GBP
            [1, 10, 'AU', null, 'AU', '2620', '', 'australia_domestic', 0],
            [1, 10, 'AU', null, 'AU', '2620', '1234', 'australia_domestic', 0], //Invalid Business No
            //Valid Business No, with GST registration
            [1, 10, 'AU', null, 'AU', '2620', '72 629 951 766', 'australia_domestic', 0],
            //Valid Business No, with GST registration
            [1, 10, 'GB', 'NE1 1AA', 'AU', '2620', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, with GST registration
            [10, 1000, 'GB', 'NE1 1AA', 'AU', '2620', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, with GST registration
            [1, 4000, 'GB', 'NE1 1AA', 'AU', '2620', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, but no GST registration
            [1, 10, 'GB', 'NE1 1AA', 'AU', '2620', '50 110 219 460', 'australia_import_taxed', 0],
            [1, 10, 'GB', 'NE1 1AA', 'AU', '2620', '1234', 'australia_import_taxed', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_taxed', 0],
            [1, 10, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_taxed', 0],
            [56, 10, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_taxed', 0],
            [5, 100, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_taxed', 0],
            [1, 560, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_taxed', 0],
            [2, 570, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_untaxed', 50],
            [1, 570, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_untaxed', 0],
            [2, 570, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_untaxed', 50],
            [9, 100, 'GB', 'NE1 1AA', 'AU', '2620', '', 'australia_import_untaxed', 0],
            [5, 4000, 'GB', 'NE1 1AA', 'AU', '2620', '1234', 'australia_import_untaxed', 0], //Invalid Business No
        ];
    }
}
