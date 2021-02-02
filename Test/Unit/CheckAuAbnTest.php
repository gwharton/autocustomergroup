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
    public function testIsValidAbn($number, $valid): void
    {
        $this->assertEquals($valid, $this->model->isValidAbn($number));
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
            ["72 629 951 766", true],
            ["90929922193", true],
            ["19621994018", true],
            ["61215203421", true],
            ["40 978 973 457", true],
            ["oygyg", false],
            ["", false]
        ];
    }
}
