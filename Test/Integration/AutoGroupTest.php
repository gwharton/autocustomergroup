<?php
declare(strict_types=1);

namespace Magento\Sales\Model\Order;

use Magento\Catalog\Api\ProductRepositoryInterface;
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
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\SalesRule\Model\RuleFactory;
use Magento\SalesRule\Model\RuleRepository;
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
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
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
    }

    /**
     * @param float $qty
     * @param string $merchantCountry
     * @param string $destinationPostcode
     * @param string $destinationCountry
     * @param string $destinationVatId
     * @param string $customerGroup
     * @param int $percentageDiscount
     *
     * £10 each, 22 in stock
     * @magentoDataFixture Magento/Catalog/_files/products.php
     * @dataProvider dataProviderForTestAutoCustomerGroup
     * @magentoConfigFixture current_store customer/create_account/auto_group_assign 1
     * @magentoConfigFixture current_store customer/create_account/tax_calculation_address_type shipping
     * @magentoConfigFixture current_store autocustomergroup/ukvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/ukvat/registrationnumber GB553557881
     * @magentoConfigFixture current_store autocustomergroup/ukvat/environment sandbox
     * @magentoConfigFixture current_store autocustomergroup/ukvat/importthreshold 40
     * @magentoConfigFixture current_store autocustomergroup/ukvat/clientid ENdwPn9Bo1kzjmdJRIwnVwJ67ws7
     * @magentoConfigFixture current_store autocustomergroup/ukvat/clientsecret d6fe5959-55cc-4354-bcfb-647bcb322ff9
     * @magentoConfigFixture current_store autocustomergroup/euvat/enabled 1
     * @magentoConfigFixture current_store autocustomergroup/euvat/importthreshold 90
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationcountry IE
     * @magentoConfigFixture current_store autocustomergroup/euvat/registrationnumber IE3206488LH
     * @magentoConfigFixture current_store general/region/state_required ""
     * //phpcs:ignore
     * @magentoConfigFixture current_store general/country/eu_countries AT,BE,BG,HR,CY,CZ,DK,EE,FI,FR,DE,GR,HU,IE,IT,LV,LT,LU,MT,MC,NL,PL,PT,RO,SK,SI,ES,SE
     * @return void
     */
    public function testAutoCustomerGroup(
        $qty,
        $merchantCountry,
        $destinationPostcode,
        $destinationCountry,
        $destinationVatId,
        $customerGroup,
        $percentageDiscount
    ): void {
        $storeId = $this->storeManager->getStore()->getId();
        $groups = [0];
        $groups[] = $this->createGroupAndAssign('uk_valid_import', 'autocustomergroup/ukvat/validimport');
        $groups[] = $this->createGroupAndAssign(
            'uk_import_above_threshold',
            'autocustomergroup/ukvat/importabovethreshold'
        );
        $groups[] = $this->createGroupAndAssign('eu_valid_intraeu', 'autocustomergroup/euvat/validintraeu');
        $groups[] = $this->createGroupAndAssign('eu_valid_import', 'autocustomergroup/euvat/validimport');
        $groups[] = $this->createGroupAndAssign(
            'eu_import_above_threshold',
            'autocustomergroup/euvat/importabovethreshold'
        );

        if ($percentageDiscount > 0) {
            $this->createSalesRule($groups, $percentageDiscount, $storeId);
        }

        $this->config->setValue(
            "general/store_information/country_id",
            $merchantCountry,
            ScopeInterface::SCOPE_STORE
        );

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
     */
    public function dataProviderForTestAutoCustomerGroup()
    {
        //Items to put in basket £10 each
        //Merchant Country
        //Destination Postcode
        //Destination Country
        //Expected Customer Group
        //Discount Percentage
        return [
            [1, 'GB', 'NE1 1AA', 'GB', '', 'NOT LOGGED IN', 0],
            [1, 'GB', 'NE1 1AA', 'GB', 'GB948561936944', 'NOT LOGGED IN', 0],
            [1, 'IM', 'NE1 1AA', 'GB', 'GB948561936944', 'NOT LOGGED IN', 0],
            [1, 'GB', 'IM1 1AA', 'IM', 'GB000549615108', 'NOT LOGGED IN', 0],
            [1, 'IM', 'IM1 1AA', 'IM', 'GB000549615108', 'NOT LOGGED IN', 0],
            [1, 'FR', 'NE1 1AA', 'GB', 'GB948561936944', 'uk_valid_import', 0],
            //10 x 10ea = 100,  * 50% = 50, Threshold is 40
            [10, 'FR', 'NE1 1AA', 'GB', 'GB948561936944', 'uk_import_above_threshold', 50],
            [1, 'GB', 'NE1 1AA', 'GB', 'GB123', 'NOT LOGGED IN', 0],
            [1, 'GB', 'NE1 1AA', 'GB', 'GB948561936943', 'NOT LOGGED IN', 0],
            [1, 'GB', '75001', 'FR', '', 'NOT LOGGED IN', 0],
            [1, 'GB', '12345', 'BR', '', 'NOT LOGGED IN', 0],
            [1, 'IE', '', 'IE', '', 'NOT LOGGED IN', 0],
            [1, 'IE', '', 'IE', 'IE8256796U', 'NOT LOGGED IN', 0],
            [1, 'GB', '', 'IE', 'IE8256796U', 'eu_valid_import', 0],
            //20 x 10ea = 200,  * 50% = 100, Threshold is 90
            [20, 'GB', '', 'IE', 'IE8256796U', 'eu_import_above_threshold', 50],
            //18 x 10ea = 180,  * 50% = 90, Threshold is 90
            [18, 'GB', '', 'IE', 'IE8256796U', 'eu_valid_import', 50],
            //16 x 10ea = 160,  * 50% = 80, Threshold is 90
            [16, 'GB', '', 'IE', 'IE8256796U', 'eu_valid_import', 50],
            [1, 'DE', '', 'IE', 'IE8256796U', 'eu_valid_intraeu', 0],
            [1, 'DE', '', 'IE', 'IE8256796H', 'NOT LOGGED IN', 0],
            [1, 'GB', '', 'IE', '123456', 'NOT LOGGED IN', 0],
            [1, 'BR', '', 'IE', '', 'NOT LOGGED IN', 0],
            [1, 'BR', '', 'IE', 'IE8256796U', 'eu_valid_import', 0],
        ];
    }
}
