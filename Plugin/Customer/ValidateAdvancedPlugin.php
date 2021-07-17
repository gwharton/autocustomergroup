<?php
namespace Gw\AutoCustomerGroup\Plugin\Customer;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Backend\Model\Session\Quote as QuoteSession;
use Magento\Customer\Controller\Adminhtml\System\Config\Validatevat\ValidateAdvanced;
use Magento\Customer\Model\Vat;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\Store;

class ValidateAdvancedPlugin
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
     * @var QuoteSession
     */
    private $quoteSession;

    /**
     * @param JsonFactory $jsonFactory
     * @param Vat $vat
     * @param RequestInterface $request
     * @param AutoCustomerGroup $autocustomergroup
     * @param QuoteSession $quoteSession
     */
    public function __construct(
        JsonFactory $jsonFactory,
        Vat $vat,
        RequestInterface $request,
        AutoCustomerGroup $autoCustomerGroup,
        QuoteSession $quoteSession
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->vat = $vat;
        $this->request = $request;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->quoteSession = $quoteSession;
    }

    /**
     * Alternative VAT Number Validation
     *
     * @param ValidateAdvanced $subject
     * @param callable $proceed
     * @return ResponseInterface|RedirectInterface|ResultInterface|void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        ValidateAdvanced $subject,
        callable $proceed
    ) {
        $storeId = (int)$this->request->getParam('store_id', Store::DEFAULT_STORE_ID);
        if ($this->autoCustomerGroup->isModuleEnabled($storeId)) {
            $countrycode = $this->request->getParam('country');
            $quote = $this->quoteSession->getQuote();
            $postcode = $this->request->getParam('postcode');
            $taxIdToCheck = $this->request->getParam('tax');

            $gatewayresponse = null;
            if (!empty($countrycode) && !empty($taxIdToCheck) && $storeId) {
                $gatewayresponse = $this->autoCustomerGroup->checkTaxId(
                    $countrycode,
                    $taxIdToCheck,
                    $storeId
                );
            }

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
        $resultJson = $this->jsonFactory->create();
        return $resultJson->setData($result);
    }
}
