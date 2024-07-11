<?php
namespace Gw\AutoCustomerGroup\Model;

use Gw\AutoCustomerGroup\Api\Data\GatewayResponseInterface;
use Magento\Framework\DataObject;

class GatewayResponse extends DataObject implements GatewayResponseInterface
{
    public function setIsValid(bool $valid): void
    {
        $this->setData('is_valid', $valid);
    }

    public function getIsValid(): bool
    {
        return $this->getData('is_valid') ?: false;
    }

    public function setRequestSuccess(bool $success): void
    {
        $this->setData('request_success', $success);
    }

    public function getRequestSuccess(): bool
    {
        return $this->getData('request_success') ?: false;
    }

    public function setRequestDate(string $date): void
    {
        $this->setData('request_date', $date);
    }

    public function getRequestDate(): string
    {
        return $this->getData('request_date') ?: "";
    }

    public function setRequestIdentifier(string $identifier): void
    {
        $this->setData('request_identifier', $identifier);
    }

    public function getRequestIdentifier(): string
    {
        return $this->getData('request_identifier') ?: "";
    }

    public function setRequestMessage(string $message): void
    {
        $this->setData('request_message', $message);
    }

    public function getRequestMessage(): string
    {
        return $this->getData('request_message') ?: "";
    }
}
