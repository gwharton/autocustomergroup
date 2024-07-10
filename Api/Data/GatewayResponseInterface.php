<?php
namespace Gw\AutoCustomerGroup\Api\Data;

interface GatewayResponseInterface
{
    /**
     * @param bool $valid
     * @return void
     */
    public function setIsValid(bool $valid): void;

    /**
     * @return bool
     */
    public function getIsValid(): bool;

    /**
     * @param bool $success
     * @return void
     */
    public function setRequestSuccess(bool $success): void;

    /**
     * @return bool
     */
    public function getRequestSuccess(): bool;

    /**
     * @param string $date
     * @return void
     */
    public function setRequestDate(string $date): void;

    /**
     * @return string
     */
    public function getRequestDate(): string;

    /**
     * @param string $identifier
     * @return void
     */
    public function setRequestIdentifier(string $identifier): void;

    /**
     * @return string
     */
    public function getRequestIdentifier(): string;

    /**
     * @param string $message
     * @return void
     */
    public function setRequestMessage(string $message): void;

    /**
     * @return string
     */
    public function getRequestMessage(): string;
}
