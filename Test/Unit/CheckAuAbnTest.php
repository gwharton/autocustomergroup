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
        //Valid numbers from https://abr.business.gov.au/Search/ResultsActive?SearchText=example
        return [
            ["50 110 219 460", true],
            ["99 644 068 913", true],
            ["36 643 591 119", true],
            ["90 929 922 193", true],
            ["58 630 144 375", true],
            ["oygyg", false],
            ["98 765 432 111", false],
            ["12 345 678 999", false],
            ["", false]
        ];
    }
}
