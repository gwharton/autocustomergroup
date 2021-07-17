<?php
namespace Gw\AutoCustomerGroup\Controller\Ajax;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;

class Validate implements HttpPostActionInterface
{
    /**
     * @var Validator
     */
    private $validator;

    /**
     * @var AutoCustomerGroup
     */
    private $autoCustomerGroup;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @param Validator $validator
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Validator $validator,
        AutoCustomerGroup $autoCustomerGroup,
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        JsonFactory $jsonFactory
    ) {
        $this->validator = $validator;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->jsonFactory = $jsonFactory;
    }

    /**
     * @return ResponseInterface|RedirectInterface|ResultInterface|void
     */
    public function execute()
    {
        $taxIdToCheck = $this->request->getParam('tax_id');
        $countrycode = $this->request->getParam('country_code');
        $storeId = (int)$this->request->getParam('store_id', 0);
        if (!$this->validator->validate($this->request)) {
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('*/*/');
        }

        $gatewayresponse = null;
        if (!empty($countrycode) && !empty($taxIdToCheck) && $storeId) {
            $gatewayresponse = $this->autoCustomerGroup->checkTaxId(
                $countrycode,
                $taxIdToCheck,
                $storeId
            );
        }

        $responsedata = [
            'valid' => false,
            'message' => __('There was an error validating your Tax Id'),
            'success' => false
        ];
        if ($gatewayresponse) {
            $responsedata = [
                'valid' => $gatewayresponse->getIsValid(),
                'message' => $gatewayresponse->getRequestMessage(),
                'success' => $gatewayresponse->getRequestSuccess()
            ];
        }
        $resultJson = $this->jsonFactory->create();
        return $resultJson->setData($responsedata);
    }
}
