<?php
namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\TaxSchemes\EuVat;
use Gw\AutoCustomerGroup\Model\TaxSchemes\UkVat;
use Magento\Customer\Controller\Adminhtml\System\Config\Validatevat\Validate;
use Magento\Customer\Model\Vat;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;

class ValidatePlugin
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

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
     * @param JsonFactory $jsonFactory
     * @param Vat $vat
     * @param RequestInterface $request
     * @param AutoCustomerGroup $autocustomergroup
     * @param EuVat $euVat
     * @param UkVat $ukVat
     */
    public function __construct(
        JsonFactory $jsonFactory,
        Vat $vat,
        RequestInterface $request,
        AutoCustomerGroup $autoCustomerGroup,
        EuVat $euVat,
        UkVat $ukVat
    ) {
        $this->jsonFactory = $jsonFactory;
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
     * @return ResponseInterface|RedirectInterface|ResultInterface|void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        Validate $subject,
        callable $proceed
    ) {
        $taxIdToCheck = $this->request->getParam('tax_id');
        $countrycode = $this->request->getParam('country_code');
        $storeId = (int)$this->request->getParam('store_id', Store::DEFAULT_STORE_ID);
        if ($this->autoCustomerGroup->isModuleEnabled($storeId)) {
            if ($this->ukVat->isSchemeCountry($countrycode)) {
                $gatewayresponse = $this->ukVat->checkTaxId(
                    $countrycode,
                    $taxIdToCheck
                );
            } elseif ($this->euVat->isSchemeCountry($countrycode)) {
                $gatewayresponse = $this->euVat->checkTaxId(
                    $countrycode,
                    $taxIdToCheck
                );
            } else {
                $gatewayresponse = new DataObject(
                    [
                        'is_valid' => false,
                        'request_message' => 'Only UK and EU VAT Numbers supported.',
                        'request_success' => false
                    ]
                );
            }
            $result = [
                'valid' => $gatewayresponse->getIsValid(),
                'message' => $gatewayresponse->getRequestMessage(),
                'success' => $gatewayresponse->getRequestSuccess()
            ];
        } else {
            $gatewayresponse = $this->vat->checkVatNumber(
                $countrycode,
                $taxIdToCheck
            );
            $result = [
                'valid' => $gatewayresponse->getIsValid(),
                'message' => $gatewayresponse->getRequestMessage()
            ];
        }
        $resultJson = $this->jsonFactory->create();
        return $resultJson->setData($result);
    }
}
