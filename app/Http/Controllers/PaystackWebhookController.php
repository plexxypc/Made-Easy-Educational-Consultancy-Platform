<?php

namespace App\Http\Controllers;

use App\Actions\Payments\ConfirmPaystackCharge;
use App\Services\Paystack\PaystackClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/paystack. Excluded from CSRF (bootstrap/app.php); the
 * x-paystack-signature HMAC is the authentication instead.
 *
 * Every request's signature is checked against the raw body before anything
 * is parsed; a bad or missing signature gets 401 and changes nothing. Valid
 * events we don't handle get 200 so Paystack stops retrying them. An exception
 * while processing gives 500, so Paystack retries (processing is idempotent).
 */
class PaystackWebhookController extends Controller
{
    /**
     * @param  Request  $request
     * @param  PaystackClient  $paystack
     * @param  ConfirmPaystackCharge  $confirm_paystack_charge
     * @return Response
     */
    public function __invoke(Request $request, PaystackClient $paystack, ConfirmPaystackCharge $confirm_paystack_charge): Response
    {
        $payload = $request->getContent();

        if (! $paystack->hasValidSignature($payload, $request->header('x-paystack-signature'))) {
            Log::warning('Rejected Paystack webhook: invalid or missing signature.', ['ip' => $request->ip()]);

            return response('Invalid signature', 401);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response('Invalid payload', 400);
        }

        if (($event['event'] ?? null) === 'charge.success') {
            $outcome = $confirm_paystack_charge->handle((array) ($event['data'] ?? []));

            Log::info('Paystack charge.success processed.', [
                'paystack_reference' => $event['data']['reference'] ?? null,
                'outcome' => $outcome,
            ]);
        }

        return response('OK', 200);
    }
}
