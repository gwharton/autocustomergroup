<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface OrderTaxCollectedInterface
{
    /**
     * @param int $orderId
     * @return array
     */
    public function getTaxCollectedDetails(int $orderId): array;
}
