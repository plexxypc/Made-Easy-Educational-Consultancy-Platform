<?php

namespace App\Http\Requests;

use App\Enums\CustomerFieldType;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * "Request this service" form: contact details plus the service's own
 * customer_fields, whose rules are built from the service definition.
 *
 * Only keys defined on the service are validated, so validated() never
 * contains extra client-supplied fields.
 */
class SubmitServiceRequest extends FormRequest
{
    /**
     * Loose phone format: optional +, then 7–20 digits/spaces/dashes/brackets.
     * Assumption: no strict Nigerian-number check, since customers may be abroad.
     *
     * @var string
     */
    public const PHONE_REGEX = '/^\+?[0-9][0-9\s\-()]{6,19}$/';

    /**
     * Only active, non-instant services accept requests.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->service()->acceptsRequests();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            'email' => ['required', 'string', 'email', 'max:255'],
        ];

        foreach ($this->service()->customer_fields ?? [] as $field) {
            $rules['fields.'.$field['key']] = self::field_rules($field);
        }

        return $rules;
    }

    /**
     * Use the service's field labels in error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['name' => 'full name', 'phone' => 'phone number', 'email' => 'email address'];

        foreach ($this->service()->customer_fields ?? [] as $field) {
            $attributes['fields.'.$field['key']] = $field['label'];
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 0803 123 4567 or +234 803 123 4567.',
        ];
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

    /**
     * Non-file answers keyed by field key (numbers stored as numbers, blanks as null).
     *
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        $validated_fields = $this->validated('fields', []);
        $answers = [];

        foreach ($this->service()->customer_fields ?? [] as $field) {
            if (self::field_type($field) === CustomerFieldType::File) {
                continue;
            }

            $value = $validated_fields[$field['key']] ?? null;

            $answers[$field['key']] = match (true) {
                $value === null || $value === '' => null,
                self::field_type($field) === CustomerFieldType::Number => $value + 0,
                default => $value,
            };
        }

        return $answers;
    }

    /**
     * Uploaded files keyed by field key (only fields that received a file).
     *
     * @return array<string, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = [];

        foreach ($this->service()->customer_fields ?? [] as $field) {
            $file = $this->file('fields.'.$field['key']);

            if (self::field_type($field) === CustomerFieldType::File && $file instanceof UploadedFile) {
                $files[$field['key']] = $file;
            }
        }

        return $files;
    }

    /**
     * The service being requested (from the route).
     *
     * @return Service
     */
    public function service(): Service
    {
        return $this->route('service');
    }

    /**
     * Validation rules for one customer field definition.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private static function field_rules(array $field): array
    {
        $rules = [! empty($field['required']) ? 'required' : 'nullable'];

        return array_merge($rules, match (self::field_type($field)) {
            CustomerFieldType::Textarea => ['string', 'max:5000'],
            CustomerFieldType::Number => array_filter([
                'numeric',
                isset($field['min']) ? 'min:'.$field['min'] : null,
                isset($field['max']) ? 'max:'.$field['max'] : null,
            ]),
            CustomerFieldType::Phone => ['string', 'regex:'.self::PHONE_REGEX],
            CustomerFieldType::Email => ['string', 'email', 'max:255'],
            CustomerFieldType::Select => ['string', Rule::in($field['options'] ?? [])],
            CustomerFieldType::File => [
                File::types(config('madeeasy.uploads.allowed_extensions'))
                    ->max(config('madeeasy.uploads.max_kilobytes')),
            ],
            default => ['string', 'max:255'],
        });
    }

    /**
     * @param  array<string, mixed>  $field
     * @return CustomerFieldType
     */
    private static function field_type(array $field): CustomerFieldType
    {
        return CustomerFieldType::tryFrom((string) ($field['type'] ?? '')) ?? CustomerFieldType::Text;
    }
}
