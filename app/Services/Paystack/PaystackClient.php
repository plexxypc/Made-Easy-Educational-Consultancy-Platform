<?php

namespace App\Services\Paystack;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Paystack API client: start a transaction, verify one, and check
 * webhook signatures. Keys come from config/services.php (PAYSTACK_* in .env).
 *
 * Amounts are always integer KOBO, which is what Paystack expects for NGN.
 */
class PaystackClient
{
    /**
     * Whether a secret key is configured (online payment is possible).
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return filled(config('services.paystack.secret_key'));
    }

    /**
     * POST /transaction/initialize.
     *
     * @param  string  $email  Customer email (required by Paystack).
     * @param  int  $amount  KOBO.
     * @param  string  $reference  Our unique payment reference.
     * @param  string  $callback_url  Where Paystack sends the browser afterwards.
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed> Paystack "data": authorization_url, access_code, reference.
     *
     * @throws PaystackException
     */
    public function initialize(string $email, int $amount, string $reference, string $callback_url, array $metadata = []): array
    {
        return $this->send('post', '/transaction/initialize', [
            'email' => $email,
            'amount' => $amount,
            'currency' => config('madeeasy.payments.currency'),
            'reference' => $reference,
            'callback_url' => $callback_url,
            'metadata' => $metadata,
        ]);
    }

    /**
     * GET /transaction/verify/{reference}.
     *
     * @param  string  $reference
     * @return array<string, mixed> Paystack "data" (status, amount, currency, ...).
     *
     * @throws PaystackException
     */
    public function verify(string $reference): array
    {
        return $this->send('get', '/transaction/verify/'.rawurlencode($reference));
    }

    /**
     * Whether a webhook body was signed by Paystack: the x-paystack-signature
     * header must equal HMAC-SHA512 of the raw body keyed with our secret key.
     * Always false when no secret is configured.
     *
     * @param  string  $payload  Raw request body.
     * @param  string|null  $signature  x-paystack-signature header.
     * @return bool
     */
    public function hasValidSignature(string $payload, ?string $signature): bool
    {
        $secret_key = (string) config('services.paystack.secret_key');

        if ($secret_key === '' || blank($signature)) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret_key), strtolower(trim($signature)));
    }

    /**
     * @param  string  $method
     * @param  string  $path
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws PaystackException
     */
    private function send(string $method, string $path, array $body = []): array
    {
        if (! $this->isConfigured()) {
            throw new PaystackException('Paystack secret key is not configured.');
        }

        try {
            $response = $this->http()->{$method}($path, $body);
        } catch (ConnectionException $exception) {
            throw new PaystackException('Could not reach Paystack: '.$exception->getMessage());
        }

        $json = (array) $response->json();

        if (! $response->successful() || ($json['status'] ?? false) !== true) {
            throw new PaystackException((string) ($json['message'] ?? "Paystack returned HTTP {$response->status()}."), $json);
        }

        return (array) ($json['data'] ?? []);
    }

    /**
     * @return PendingRequest
     */
    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paystack.base_url'))
            ->withToken((string) config('services.paystack.secret_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }
}
