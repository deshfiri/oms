<?php

namespace App\Services\Couriers\FraudCheck;

/**
 * A fraud-check failure whose message is SAFE to show to the operator.
 * Technical detail (raw bodies, exception chains) belongs in the log,
 * never in this message.
 */
class FraudCheckException extends \RuntimeException
{
    /**
     * @param string $code machine code for the audit log / frontend:
     *                     no_courier | unsupported | not_configured |
     *                     courier_error | invalid_response | timeout
     */
    public function __construct(string $userMessage, public readonly string $errorCode = 'courier_error')
    {
        parent::__construct($userMessage);
    }
}
