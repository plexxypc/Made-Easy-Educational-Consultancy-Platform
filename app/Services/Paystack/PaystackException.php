<?php

namespace App\Services\Paystack;

use RuntimeException;

/**
 * Paystack could not be reached, or refused the request.
 */
class PaystackException extends RuntimeException
{
    /**
     * @param  string  $message
     * @param  array<string, mixed>  $response  Paystack's response body, if any (for payments.raw_response).
     */
    public function __construct(string $message, public readonly array $response = [])
    {
        parent::__construct($message);
    }
}
