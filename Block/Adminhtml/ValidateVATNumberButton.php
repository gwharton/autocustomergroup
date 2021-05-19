<?php
namespace Gw\AutoCustomerGroup\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Backend\Block\Widget\Form\Renderer\Fieldset\Element;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Helper\SecureHtmlRenderer;

class ValidateVATNumberButton extends Element
{
    /**
     * Validate button block
     *
     * @var null|Button
     */
    protected $_validateButton = null;

    /**
     * @var string
     */
    protected $_template = 'Magento_Customer::sales/order/create/address/form/renderer/vat.phtml';

    /**
     * @var Json
     */
    protected $serializer;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var SecureHtmlRenderer
     */
    private $secureRenderer = null;

    /**
     * @param Context $context
     * @param Json $serializer
     * @param ObjectManager $objectManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        Json $serializer,
        ObjectManager $objectManager,
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->objectManager = $objectManager;
        if (class_exists(SecureHtmlRenderer::class)) {
            $this->secureRenderer = $this->objectManager->create(SecureHtmlRenderer::class);
        }
        parent::__construct($context, $data);
    }

    /**
     * Retrieve validate button block
     *
     * @return \Magento\Backend\Block\Widget\Button
     */
    public function getValidateButton()
    {
        if ($this->_validateButton === null) {
            /** @var $form Form */
            $form = $this->_element->getForm();

            $taxIdElementId = $this->_element->getHtmlId();
            $countryElementId = $form->getElement('country_id')->getHtmlId();
            $postcodeElementId = $form->getElement('postcode')->getHtmlId();
            $validateUrl = $this->_urlBuilder->getUrl('customer/system_config_validatevat/validateAdvanced');

            $groupMessage = __(
                'The customer is now assigned to Customer Group %s.'
            ) . ' ' . __(
                'Would you like to change the Customer Group for this order?'
            );

            $validateOptions = $this->serializer->serialize(
                [
                    'taxIdElementId' => $taxIdElementId,
                    'countryElementId' => $countryElementId,
                    'postcodeElementId' => $postcodeElementId,
                    'groupIdHtmlId' => 'group_id',
                    'validateUrl' => $validateUrl,
                    'taxIdValidMessage' => __('The TAX ID is valid.'),
                    'taxIdInvalidMessage' => __('The TAX ID entered (%s) is not a valid TAX ID.'),
                    'taxIdValidAndGroupValidMessage' => __(
                        'The TAX ID is valid. The current Customer Group will be used.'
                    ),
                    'taxIdValidAndGroupChangeMessage' => __(
                        'Based on the TAX ID, the customer belongs to the Customer Group %s.'
                    ) . "\n" . $groupMessage,
                    'taxIdValidationFailedMessage' => __(
                        'Something went wrong while validating the TAX ID.'
                    ),
                    'taxIdCustomerGroupMessage' => __(
                        'The customer would belong to Customer Group %s.'
                    ),
                    'taxIdGroupErrorMessage' => __('There was an error detecting Customer Group.'),
                ]
            );

            $optionsVarName = $this->getJsVariablePrefix() . 'Parameters';
            $scriptString = 'var ' . $optionsVarName . ' = ' . $validateOptions . ';';

            if ($this->secureRenderer) {
                $beforeHtml = $this->secureRenderer->renderTag('script', [], $scriptString, false);
            } else {
                $beforeHtml = "<script>" . $scriptString . "</script>";
            }

            $block = $this->getLayout()->createBlock(
                Button::class
            );
            /** @var DataObject $block */
            $this->_validateButton = $block->setData(
                [
                    'label' => __('Validate TAX Identifier'),
                    'before_html' => $beforeHtml,
                    'onclick' => 'order.validateTaxId(' . $optionsVarName . ')',
                ]
            );
        }

        return $this->_validateButton;
    }
}
