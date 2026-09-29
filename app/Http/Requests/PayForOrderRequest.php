<?php

namespace App\Http\Requests;

/**
 * "Pay now" on the order page or My Requests. No input: the amount always
 * comes from the order (orders.total_amount), never from the browser.
 */
class PayForOrderRequest extends TrackedOrderRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
