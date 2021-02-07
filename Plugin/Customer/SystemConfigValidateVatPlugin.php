<?php
namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\TaxSchemes\EuVat;
use Gw\AutoCustomerGroup\Model\TaxSchemes\UkVat;
use Magento\Customer\Controller\Adminhtml\System\Config\Validatevat\Validate;
use Magento\Customer\Model\Vat;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;

class SystemConfigValidateVatPlugin
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var Vat
     */
    private $vat;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var EuVat
     */
    private $euVat;

    /**
     * @var UkVat
     */
    private $ukVat;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param Vat $vat
     * @param RequestInterface $request
     * @param AutoCustomerGroup $autocustomergroup
     * @param EuVat $euVat
     * @param UkVat $ukVat
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Vat $vat,
        RequestInterface $request,
        AutoCustomerGroup $autoCustomerGroup,
        EuVat $euVat,
        UkVat $ukVat
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->vat = $vat;
        $this->request = $request;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->euVat = $euVat;
        $this->ukVat = $ukVat;
    }

    /**
     * Alternative VAT Number Validation
     *
     * @param Validate $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        Validate $subject,
        callable $proceed
    ) {
        if ($this->autoCustomerGroup->isModuleEnabled(Store::DEFAULT_STORE_ID)) {
            $country = $this->request->getParam('country');
            if ($this->ukVat->isSchemeCountry($country)) {
                $result = $this->ukVat->checkTaxId(
                    $country,
                    $this->request->getParam('vat')
                );
            } elseif ($this->euVat->isSchemeCountry($country)) {
                $result = $this->euVat->checkTaxId(
                    $country,
                    $this->request->getParam('vat')
                );
            } else {
                $result = new DataObject(
                    [
                        'is_valid' => false,
                        'request_message' => 'Only UK and EU VAT Numbers supported.'
                    ]
                );
            }
        } else {
            $result = $this->vat->checkVatNumber(
                $this->request->getParam('country'),
                $this->request->getParam('vat')
            );
        }
        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData([
            'valid' => (int)$result->getIsValid(),
            'message' => $result->getRequestMessage(),
        ]);
    }
}
