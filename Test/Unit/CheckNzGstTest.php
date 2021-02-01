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
     * @return void
     * @dataProvider isValidGstDataProvider
     */
    public function testIsValidGst($number): void
    {
        $this->assertTrue($this->model->isValidGst($number));
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
            ["49091850"],
            ["123123123"]
        ];
    }
}
