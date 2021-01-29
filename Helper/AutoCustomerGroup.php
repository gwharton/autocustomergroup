<?php
namespace Gw\AutoCustomerGroup\Helper;

use Magento\Customer\Model\GroupManagement;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Information as StoreInformation;
use Magento\Store\Model\ScopeInterface;

class AutoCustomerGroup
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param int $storeId
     * @return int
     */
    public function getDefaultGroup($storeId = null)
    {
        return $this->scopeConfig->getValue(
            GroupManagement::XML_PATH_DEFAULT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Retrieve merchant country code
     *
     * @param Store|string|int|null $store
     * @return string
     */
    public function getMerchantCountryCode($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            StoreInformation::XML_PATH_STORE_INFO_COUNTRY_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
