<?php
namespace Gw\AutoCustomerGroup\Test\Unit;

use Gw\AutoCustomerGroup\Model\TaxSchemes\NewZealandGst;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use PHPUnit\Framework\TestCase;

class CheckNzGstTest extends TestCase
{
    /**
     * @var NewZealandGst
     */
    private $model;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->model = (new ObjectManagerHelper($this))->getObject(
            NewZealandGst::class,
            []
        );
    }

    /**
     * @param $number
     * @param $valid
     * @return void
     * @dataProvider isValidGstDataProvider
     */
    public function testIsValidGst($number, $valid): void
    {
        $this->assertEquals($valid, $this->model->isValidGst($number));
    }

    /**
     * Data provider for testIsValidGst()
     *
     * @return array
     */
    public function isValidGstDataProvider(): array
    {
        //Really need more Test GST numbers for this.
        return [
            ["49091850", true],
            ["123123123", true],
            ["123456789", false],
            ["ghkk", false],
            ["", false],
            [null, false]
        ];
    }

    /**
     * @param $number
     * @param $valid
     * @return void
     * @dataProvider isValidNZBNDataProvider
     */
    public function testIsValidNzbn($number, $valid): void
    {
        $this->assertEquals($valid, $this->model->isValidNzbn($number));
    }

    /**
     * Data provider for testIsValidNzbn()
     *
     * @return array
     */
    public function isValidNZBNDataProvider(): array
    {
        return [
            ["9429039098740", true],
            ["9429034243282", true],
            ["9429041535110", true],
            ["9429049999198", true],
            ["6291041500213", true],
            ["6291041500212", false],
            ["123456789", false],
            ["ghkk", false],
            ["", false],
            [null, false]
        ];
    }
}

