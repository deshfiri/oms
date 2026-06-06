<?php

namespace App\Services\Storefront;

use RuntimeException;

class StorefrontApiException extends RuntimeException
{
    public function __construct(public int $status, string $message = '', public ?array $body = null)
    {
        parent::__construct($message ?: "Storefront API error {$status}", $status);
    }
}
