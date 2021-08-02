<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface GatewayResponseInterface
{
    public function setIsValid(bool $valid);
    public function getIsValid(): bool;

    public function setRequestSuccess(bool $success);
    public function getRequestSuccess(): bool;

    public function setRequestDate(string $date);
    public function getRequestDate(): string;

    public function setRequestIdentifier(string $identifier);
    public function getRequestIdentifier(): string;

    public function setRequestMessage(string $message);
    public function getRequestMessage(): string;
}
