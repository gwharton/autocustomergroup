<?php
namespace Gw\AutoCustomerGroup\Controller\Ajax;

use Gw\AutoCustomerGroup\Model\AutoCustomerGroup;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\App\Response\HttpFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Serialize\SerializerInterface;

class Validate implements HttpPostActionInterface
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

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
     * @var HttpFactory
     */
    private $httpFactory;

    /**
     * @param SerializerInterface $serializer
     * @param Validator $validator
     * @param AutoCustomerGroup $autoCustomerGroup
     * @param RequestInterface $request
     * @param RedirectFactory $redirectFactory
     * @param HttpFactory $httpFactory
     */
    public function __construct(
        SerializerInterface $serializer,
        Validator $validator,
        AutoCustomerGroup $autoCustomerGroup,
        RequestInterface $request,
        RedirectFactory $redirectFactory,
        HttpFactory $httpFactory
    ) {
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->autoCustomerGroup = $autoCustomerGroup;
        $this->request = $request;
        $this->redirectFactory = $redirectFactory;
        $this->httpFactory = $httpFactory;
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $taxIdToCheck = $this->request->getParam('tax_id');
        $countrycode = $this->request->getParam('country_code');
        $storeId = (int)$this->request->getParam('store_id', 0);
        if (!$this->validator->validate($this->request) ||
            !$taxIdToCheck ||
            !$countrycode) {
            /** @var RedirectInterface $redirect */
            $redirect = $this->redirectFactory->create();
            return $redirect->setPath('*/*/');
        }

        $gatewayresponse = $this->autoCustomerGroup->checkTaxId(
            $countrycode,
            $taxIdToCheck,
            $storeId
        );

        $responsedata = [
            'is_valid' => false,
            'request_message' => __('There was an error validating your Tax Id'),
        ];
        if ($gatewayresponse) {
            $responsedata = [
                'is_valid' => $gatewayresponse->getIsValid(),
                'request_message' => $gatewayresponse->getRequestMessage(),
            ];
        }
        /** @var Http $response */
        $response = $this->httpFactory->create();
        $response->setHeader('cache-control', 'no-store', true);
        $response->representJson($this->serializer->serialize($responsedata));
        return $response;
    }
}
