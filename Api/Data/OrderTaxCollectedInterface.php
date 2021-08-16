<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface OrderTaxCollectedInterface
{
    public function getTaxCollectedDetails(int $orderId): array;
}
