<?php
namespace Gw\AutoCustomerGroup\Model\Config\Source;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Customer\Attribute\Source\GroupSourceLoggedInOnlyInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Convert\DataObject;
use Magento\Framework\Data\OptionSourceInterface;

class Group implements OptionSourceInterface
{
    /**
     * @var array
     */
    protected $_options;

    /**
     * @var GroupManagementInterface
     */
    protected $_groupManagement;

    /**
     * @var DataObject
     */
    protected $_converter;

    /**
     * @var GroupSourceLoggedInOnlyInterface
     */
    private $groupSourceLoggedInOnly;

    /**
     * @param GroupManagementInterface $groupManagement
     * @param DataObject $converter
     * @param GroupSourceLoggedInOnlyInterface $groupSourceForLoggedInCustomers
     */
    public function __construct(
        GroupManagementInterface $groupManagement,
        DataObject $converter,
        GroupSourceLoggedInOnlyInterface $groupSourceForLoggedInCustomers = null
    ) {
        $this->_groupManagement = $groupManagement;
        $this->_converter = $converter;
        $this->groupSourceLoggedInOnly = $groupSourceForLoggedInCustomers
            ?: ObjectManager::getInstance()->get(GroupSourceLoggedInOnlyInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        if (!$this->_options) {
            $this->_options = $this->groupSourceLoggedInOnly->toOptionArray();
            array_unshift($this->_options, ['value' => 0, 'label' => __('NOT LOGGED IN')]);
        }

        return $this->_options;
    }
}
