<?php
namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Gw\AutoCustomerGroup\Model\TaxSchemes\EuVat;
use Gw\AutoCustomerGroup\Model\TaxSchemes\UkVat;
use Magento\Backend\Model\Session\Quote as QuoteSession;
use Magento\Customer\Controller\Adminhtml\System\Config\Validatevat\ValidateAdvanced;
use Magento\Customer\Model\Vat;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteRepository;
use Magento\Store\Model\Store;

class ValidateAdvancedPlugin
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
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var QuoteSession
     */
    private $quoteSession;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param Vat $vat
     * @param RequestInterface $request
     * @param AutoCustomerGroup $autocustomergroup
     * @param EuVat $euVat
     * @param UkVat $ukVat
     * @param QuoteRepository $quoteRepository
     * @param QuoteFactory $quoteFactory
     * @param QuoteSession $quoteSession
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Vat $vat,
        RequestInterface $request,
        AutoCustomerGroup $autoCustomerGroup,
        EuVat $euVat,
        UkVat $ukVat,
        QuoteRepository $quoteRepository,
        QuoteFactory $quoteFactory,
        QuoteSession $quoteSession
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->vat = $vat;
        $this->request = $request;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->euVat = $euVat;
        $this->ukVat = $ukVat;
        $this->quoteRepository = $quoteRepository;
        $this->quoteFactory = $quoteFactory;
        $this->quoteSession = $quoteSession;
    }

    /**
     * Alternative VAT Number Validation
     *
     * @param ValidateAdvanced $subject
     * @param callable $proceed
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        ValidateAdvanced $subject,
        callable $proceed
    ) {
        $countrycode = $this->request->getParam('country');
        $storeId = (int)$this->request->getParam('store_id', Store::DEFAULT_STORE_ID);
        if ($this->autoCustomerGroup->isModuleEnabled($storeId)) {
            $quote = $this->quoteSession->getQuote();
            $postcode = $this->request->getParam('postcode');
            $taxIdToCheck = $this->request->getParam('tax');
            $gatewayresponse = $this->autoCustomerGroup->checkTaxId(
                $countrycode,
                $taxIdToCheck,
                $storeId
            );

            $result = [
                'valid' => false,
                'group' => null,
                'message' => __('Error checking TAX Identifier'),
                'success' => false
            ];

            if ($gatewayresponse && $quote) {
                $groupId = $this->autoCustomerGroup->getCustomerGroup(
                    $countrycode,
                    $postcode,
                    $gatewayresponse,
                    $quote,
                    $storeId
                );
                $result = [
                    'valid' => $gatewayresponse->getIsValid(),
                    'group' => (int)$groupId,
                    'message' => $gatewayresponse->getRequestMessage(),
                    'success' => $gatewayresponse->getRequestSuccess()
                ];
            }
        } else {
            $taxIdToCheck = $this->request->getParam('vat');
            $gatewayresponse = $this->vat->checkVatNumber(
                $countrycode,
                $taxIdToCheck
            );
            $groupId = $this->vat->getCustomerGroupIdBasedOnVatNumber(
                $countrycode,
                $gatewayresponse,
                $storeId
            );
            $result = [
                'valid' => $gatewayresponse->getIsValid(),
                'group' => (int)$groupId,
                'success' => $gatewayresponse->getRequestSuccess()
            ];
        }
        /** @var Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($result);
    }
}
