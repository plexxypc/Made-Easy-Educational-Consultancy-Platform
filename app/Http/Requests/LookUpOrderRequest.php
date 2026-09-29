<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Track your request" lookup: order reference plus the email or phone used.
 */
class LookUpOrderRequest extends FormRequest
{
    /**
     * Public form; the reference + contact match is the access check.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference' => 'reference number',
            'contact' => 'email or phone number',
        ];
    }
}
