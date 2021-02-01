<?php
namespace Gw\AutoCustomerGroup\Test\Unit;

use Gw\AutoCustomerGroup\Model\TaxSchemes\AustraliaGst;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\TestCase;

class CheckAuAbnTest extends TestCase
{
    /**
     * @var AustraliaGst
     */
    private $model;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->model = (new ObjectManagerHelper($this))->getObject(
            AustraliaGst::class,
            []
        );
    }

    /**
     * @return void
     * @dataProvider isValidAbnDataProvider
     */
    public function testIsValidAbn($number): void
    {
        $this->assertTrue($this->model->isValidAbn($number));
    }

    /**
     * Data provider for testIsValidAbn()
     *
     * @return array
     */
    public function isValidAbnDataProvider(): array
    {
        //Really need more Test ABN numbers for this.
        return [
            ["72 629 951 766"],
            ["90929922193"],
            ["19621994018"],
            ["61215203421"],
            ["40 978 973 457"]
        ];
    }
}
