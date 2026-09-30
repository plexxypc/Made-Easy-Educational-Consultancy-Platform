<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Book a consultation": the chosen slot and contact details (same rules as
 * the other order forms). Whether the slot is actually free is decided under
 * a row lock by ReserveConsultationSlot, not here.
 */
class BookConsultationRequest extends FormRequest
{
    /**
     * Anyone may book; guest checkout is allowed.
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
            'slot' => ['required', 'integer', 'min:1'],
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
        return ['slot' => 'consultation time', 'name' => 'full name', 'phone' => 'phone number', 'email' => 'email address'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slot.required' => 'Choose a date and time for your consultation.',
            'phone.regex' => 'Enter a valid phone number, e.g. 0803 123 4567 or +234 803 123 4567.',
        ];
    }

    /**
     * @return int
     */
    public function slot_id(): int
    {
        return (int) $this->validated('slot');
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
