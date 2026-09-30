<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Buy Result Checker": which checker, how many, and the contact details
 * (same rules as the generic request form). Nothing else is asked.
 */
class BuyResultCheckerRequest extends FormRequest
{
    /**
     * Anyone may buy; guest checkout is allowed.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'service' => [
                'required',
                'string',
                Rule::in(Service::query()->resultCheckersForSale()->pluck('slug')->all()),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.(int) config('madeeasy.pins.max_quantity_per_order')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.SubmitServiceRequest::PHONE_REGEX],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['service' => 'result checker', 'name' => 'full name', 'phone' => 'phone number', 'email' => 'email address'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'service.required' => 'Choose which result checker you want.',
            'service.in' => 'That result checker is not available right now. Please choose another.',
            'phone.regex' => 'Enter a valid phone number, e.g. 0803 123 4567 or +234 803 123 4567.',
        ];
    }

    /**
     * The chosen result checker service.
     *
     * @return Service
     */
    public function service(): Service
    {
        return Service::query()->resultCheckersForSale()->where('slug', $this->validated('service'))->firstOrFail();
    }

    /**
     * @return int
     */
    public function quantity(): int
    {
        return (int) $this->validated('quantity');
    }

    /**
     * Contact details for the order's guest_* columns.
     *
     * @return array{name: string, email: string, phone: string}
     */
    public function contact(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'email' => (string) $this->validated('email'),
            'phone' => (string) $this->validated('phone'),
        ];
    }
}
