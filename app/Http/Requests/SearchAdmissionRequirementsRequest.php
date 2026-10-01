<?php

namespace App\Http\Requests;

use App\Enums\NigerianState;
use App\Models\AdmissionRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Course & cut-off search (GET). Every field is optional: fewer answers
 * give broader results.
 */
class SearchAdmissionRequirementsRequest extends FormRequest
{
    /**
     * A bad value sends the student back to the empty form with the errors
     * (redirecting "back" on a GET could loop on the same URL).
     *
     * @var string
     */
    protected $redirectRoute = 'admission.search';

    /**
     * Public page.
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
            'score' => ['nullable', 'integer', 'min:0', 'max:'.AdmissionRequirement::MAX_SCORE],
            'credits' => ['nullable', 'integer', 'min:0', 'max:'.AdmissionRequirement::MAX_CREDITS],
            'course' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::enum(NigerianState::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'score' => 'JAMB score',
            'credits' => "number of O'Level credits",
            'course' => 'course',
            'state' => 'state',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.integer' => 'Enter your JAMB score as a whole number, e.g. 245.',
            'score.max' => 'JAMB scores go up to '.AdmissionRequirement::MAX_SCORE.'.',
        ];
    }

    /**
     * Whether the student has submitted the form (even with every field blank).
     *
     * @return bool
     */
    public function has_searched(): bool
    {
        return $this->hasAny(['score', 'credits', 'course', 'state']);
    }

    /**
     * @return int|null
     */
    public function score(): ?int
    {
        return $this->filled('score') ? (int) $this->validated('score') : null;
    }

    /**
     * @return int|null
     */
    public function credits(): ?int
    {
        return $this->filled('credits') ? (int) $this->validated('credits') : null;
    }

    /**
     * @return string|null
     */
    public function course_text(): ?string
    {
        return $this->filled('course') ? (string) $this->validated('course') : null;
    }

    /**
     * @return NigerianState|null
     */
    public function state_choice(): ?NigerianState
    {
        return $this->filled('state') ? NigerianState::from((string) $this->validated('state')) : null;
    }
}
