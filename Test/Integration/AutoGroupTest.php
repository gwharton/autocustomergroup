<?php
declare(strict_types=1);

namespace Magento\Sales\Model\Order;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\GuestCartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AutoGroupTest extends \PHPUnit\Framework\TestCase
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
        $this->quoteFactory = $this->objectManager->get(QuoteFactory::class);
        $this->ruleRepository = $this->objectManager->get(RuleRepository::class);
        $this->ruleFactory = $this->objectManager->get(RuleFactory::class);
        $this->addressFactory = $this->objectManager->get(AddressFactory::class);
        $this->productFactory = $this->objectManager->get(ProductFactory::class);
    }

    /**
     * @param float $qty
     * @param float $price
     * @param string $merchantCountry
     * @param string $merchantPostCode
     * @param string $destinationCountry
     * @param string $destinationPostcode
     * @param string $destinationVatId
     * @param string $customerGroup
     * @param int $percentageDiscount
     *
     * @dataProvider dataProviderForTestAutoCustomerGroup
     * @magentoConfigFixture current_store customer/create_account/auto_group_assign 1
     * @magentoConfigFixture current_store customer/create_account/tax_calculation_address_type shipping
     * @magentoConfigFixture current_store autocustomergroup/ukvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 40
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/euvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/euvat/importthreshold 90
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationcountry IE
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationnumber IE3206488LH
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/registrationnumber 12345
     * @magentoConfigFixture current_store autocustomergroup/norwayvoec/importthreshold 3000
     * @magentoConfigFixture current_store autocustomergroup/australiagst/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/australiagst/importthreshold 1000
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/newzealandgst/importthreshold 1000
     * @magentoConfigFixture current_store general/region/state_required ""
     * //phpcs:ignore
     * @magentoConfigFixture current_store general/country/eu_countries AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,MC,NL,PL,PT,RO,SK,SI,ES,SE
     * @return void
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function testAutoCustomerGroup(
        $qty,
        $price,
        $merchantCountry,
        $merchantPostCode,
        $destinationCountry,
        $destinationPostcode,
        $destinationVatId,
        $customerGroup,
        $percentageDiscount
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
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
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
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
     */
    public function createGroupAndAssign($code, $assign)
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
            $this->objectManager->get(\Magento\Framework\Api\SearchCriteriaInterface::class)
        );
        foreach ($allRules->getItems() as $rule) {
            $this->ruleRepository->deleteById($rule->getRuleId());
        }

        $ruleData =  [
            'name' => 'Discount',
            'is_active' => 1,
            'customer_group_ids' => $groupIds,
            'coupon_type' => \Magento\SalesRule\Model\Rule::COUPON_TYPE_NO_COUPON,
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
    public function dataProviderForTestAutoCustomerGroup()
    {
        //Item Qty
        //Item Price
        //Merchant Country
        //Merchant Postcode
        //Destination Country
        //Destination Postcode
        //Expected Customer Group
        //Discount Percentage
        return [
            [1, 10, 'GB', '', 'BR', '12345', '', 'NOT LOGGED IN', 0],
            [1, 10, 'FR', '75001', 'BR', '12345', '', 'NOT LOGGED IN', 0],

            //Australia GST
            [1, 10, 'AU', '', 'AU', '1234', '', 'australia_domestic', 0],
            [1, 10, 'AU', '', 'AU', '1234', '1234', 'australia_domestic', 0], //Invalid Business No
            //Valid Business No, with GST registration
            [1, 10, 'AU', '', 'AU', '1234', '72 629 951 766', 'australia_domestic', 0],
            //Valid Business No, with GST registration
            [1, 10, 'GB', 'NE1 1AA', 'AU', '1234', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, with GST registration
            [10, 1000, 'GB', 'NE1 1AA', 'AU', '1234', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, with GST registration
            [1, 4000, 'GB', 'NE1 1AA', 'AU', '1234', '72 629 951 766', 'australia_import_b2b', 0],
            //Valid Business No, but no GST registration
            [1, 10, 'GB', 'NE1 1AA', 'AU', '1234', '40 978 973 457', 'australia_import_taxed', 0],
            [1, 10, 'GB', 'NE1 1AA', 'AU', '1234', '1234', 'australia_import_taxed', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_taxed', 0],
            [9, 100, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_taxed', 0],
            [1, 1000, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_taxed', 0],
            [10, 100, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_taxed', 0],
            [1, 2000, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_untaxed', 0],
            [5, 250, 'GB', 'NE1 1AA', 'AU', '1234', '', 'australia_import_untaxed', 0],
            [5, 4000, 'GB', 'NE1 1AA', 'AU', '1234', '1234', 'australia_import_untaxed', 0], //Invalid Business No

            //New Zealand GST
            [1, 10, 'NZ', '', 'NZ', '1234', '', 'newzealand_domestic', 0],
            [1, 10, 'NZ', '', 'NZ', '1234', '1234', 'newzealand_domestic', 0], //Invalid Business No
            [1, 10, 'NZ', '', 'NZ', '1234', '49-091-850', 'newzealand_domestic', 0], //Valid Business No
            [1, 10, 'NZ', '', 'NZ', '1234', '49091850', 'newzealand_domestic', 0], //Valid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NZ', '1234', '49091850', 'newzealand_import_b2b', 0], //Valid Business No
            [10, 1000, 'GB', 'NE1 1AA', 'NZ', '1234', '49091850', 'newzealand_import_b2b', 0], //Valid Business No
            [1, 4000, 'GB', 'NE1 1AA', 'NZ', '1234', '49091850', 'newzealand_import_b2b', 0], //Valid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NZ', '1234', '1234', 'newzealand_import_taxed', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_taxed', 0],
            [9, 100, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_taxed', 0],
            [5, 250, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_taxed', 0],
            [5, 1000, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_taxed', 0],
            [1, 2000, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_untaxed', 0],
            [5, 1001, 'GB', 'NE1 1AA', 'NZ', '1234', '', 'newzealand_import_untaxed', 0],
            [5, 4000, 'GB', 'NE1 1AA', 'NZ', '1234', '1234', 'newzealand_import_untaxed', 0], //Invalid Business No

            //Norway VOEC
            [1, 10, 'NO', '1234', 'NO', '1234', '', 'norway_domestic', 0],
            [1, 10, 'NO', '1234', 'NO', '1234', '912345678', 'norway_domestic', 0], //Valid Business No
            [1, 10, 'NO', '1234', 'NO', '1234', '2443', 'norway_domestic', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1234', '912345678', 'norway_import_b2b', 0], //Valid Business No
            [10, 1000, 'GB', 'NE1 1AA', 'NO', '1234', '912345678', 'norway_import_b2b', 0], //Valid Business No
            [1, 4000, 'GB', 'NE1 1AA', 'NO', '1234', '812345678', 'norway_import_b2b', 0], //Valid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1234', '2443', 'norway_import_taxed', 0], //Invalid Business No
            [1, 10, 'GB', 'NE1 1AA', 'NO', '1234', '', 'norway_import_taxed', 0],
            [10, 1000, 'GB', 'NE1 1AA', 'NO', '1234', '', 'norway_import_taxed', 0],
            [1, 4000, 'GB', 'NE1 1AA', 'NO', '1234', '', 'norway_import_untaxed', 0],
            [5, 4000, 'GB', 'NE1 1AA', 'NO', '1234', '', 'norway_import_untaxed', 0],
            [5, 4000, 'GB', 'NE1 1AA', 'NO', '1234', '2443', 'norway_import_untaxed', 0], //Invalid Business No

            //UK VAT
            [1, 10, 'GB', '', 'GB', 'NE1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', 'BT1 1AA', 'GB', 'NE1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', 'NE1 1AA', 'GB', 'BT1 1AA', '', 'uk_domestic', 0],
            [1, 10, 'GB', '', 'GB', 'NE1 1AA', 'GB948561936944', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'IM', '', 'GB', 'NE1 1AA', 'GB948561936944', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'GB', '', 'IM', 'IM1 1AA', 'GB000549615108', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'IM', '', 'IM', 'IM1 1AA', 'GB000549615108', 'uk_domestic', 0], //VAT is valid
            [1, 10, 'GB', '', 'GB', 'NE1 1AA', 'GB123', 'uk_domestic', 0], //VAT is invalid
            [1, 10, 'GB', '', 'GB', 'NE1 1AA', 'GB948561936943', 'uk_domestic', 0], //VAT is invalid
            [1, 10, 'FR', '75001', 'GB', 'BT1 1AA', 'GB948561936944', 'uk_intraeu_b2b', 0], //VAT is invalid
            [1, 10, 'FR', '75001', 'GB', 'BT1 1AA', '', 'uk_intraeu_b2c', 0],
            [1, 10, 'FR','75001',  'GB', 'NE1 1AA', 'GB948561936944', 'uk_import_b2b', 0], //VAT is valid
            [10, 10, 'FR', '75001', 'GB', 'NE1 1AA', 'GB948561936944', 'uk_import_b2b', 0], //VAT is valid
            //1 x 10ea = 10, Threshold is 40
            [1, 10, 'FR', '75001', 'GB', 'NE1 1AA', '', 'uk_import_taxed', 0],
            //5 x 10ea = 50 * 50% = 25, Threshold is 40
            [5, 10, 'FR', '75001', 'GB', 'NE1 1AA', '', 'uk_import_taxed', 50],
            //10 x 10ea = 100, Threshold is 40
            [10, 10, 'FR', '75001', 'GB', 'NE1 1AA', '', 'uk_import_untaxed', 0],
            //10 x 10ea = 100 * 50% = 50, Threshold is 40
            [10, 10, 'FR', '75001', 'GB', 'NE1 1AA', '', 'uk_import_untaxed', 50],

            //EU VAT
            [1, 10, 'IE', '', 'IE', '', '', 'eu_domestic', 0],
            [1, 10, 'IE', '', 'IE', '', 'IE8256796U', 'eu_domestic', 0], //VAT is valid
            [1, 10, 'DE', '', 'IE', '', 'IE8256796U', 'eu_intraeu_b2b', 0], //VAT is valid
            [1, 10, 'GB', 'BT1 1AA', 'IE', '', 'IE8256796U', 'eu_intraeu_b2b', 0], //VAT is valid
            [1, 10, 'DE', '', 'IE', '', 'IE8256796H', 'eu_intraeu_b2c', 0], //VAT is invalid
            [1, 10, 'GB', 'BT1 1AA', 'IE', '', '', 'eu_intraeu_b2c', 0],
            [1, 10, 'GB', '', 'IE', '', 'IE8256796U', 'eu_import_b2b', 0], //VAT is valid
            //20 x 10ea = 200,  * 50% = 100, Threshold is 90
            [20, 10, 'GB', '', 'IE', '', 'IE8256796U', 'eu_import_b2b', 50], //VAT is valid
            //18 x 10ea = 180,  * 50% = 90, Threshold is 90
            [18, 10, 'GB', '', 'IE', '', 'IE8256796U', 'eu_import_b2b', 50], //VAT is valid
            //16 x 10ea = 160,  * 50% = 80, Threshold is 90
            [16, 10, 'GB', '', 'IE', '','IE8256796U', 'eu_import_b2b', 50], //VAT is valid
            [1, 10, 'BR', '', 'IE', '', 'IE8256796U', 'eu_import_b2b', 0], //VAT is valid
            [1, 10, 'GB', '', 'FR', '75001', '', 'eu_import_taxed', 0],
            [1, 10, 'GB', '', 'IE', '', '123456', 'eu_import_taxed', 0], //VAT is invalid
            [1, 10, 'BR', '', 'IE', '', '', 'eu_import_taxed', 0],
            //10 x 10ea = 100, Threshold is 90
            [10, 10, 'GB', '', 'FR', '75001', '', 'eu_import_untaxed', 0],
            //20 x 10ea = 200 * 50% = 100, Threshold is 90
            [20, 10, 'GB', '', 'FR', '75001', '', 'eu_import_untaxed', 50],
        ];
    }
}
