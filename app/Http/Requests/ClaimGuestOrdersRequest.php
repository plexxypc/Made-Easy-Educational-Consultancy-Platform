<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Add them to my account" on the dashboard's guest-order prompt. The ids are
 * only the ones the customer was shown; ClaimGuestOrders decides which of them
 * really are theirs to claim.
 */
class ClaimGuestOrdersRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'max:100'],
            'orders.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function order_ids(): array
    {
        return array_map('intval', (array) $this->validated('orders'));
    }
}
