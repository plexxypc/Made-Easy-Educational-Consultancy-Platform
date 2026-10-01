<?php

namespace App\Http\Requests;

use App\Models\Testimonial;

/**
 * "Share your experience" on a completed order.
 *
 * Access as for the order page (TrackedOrderRequest): the order's owner, or
 * a visitor who looked it up in this session. There is no open public form.
 * Whether the order accepts a testimonial is checked in SubmitTestimonial.
 *
 * Spam: rate limited per IP (order-responses) plus a honeypot field
 * ("website") that people never see; bots that fill it are dropped
 * silently (TestimonialController::store).
 */
class SubmitTestimonialRequest extends TrackedOrderRequest
{
    /**
     * Hidden field real visitors leave empty.
     *
     * @var string
     */
    public const HONEYPOT_FIELD = 'website';

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'min:2', 'max:80'],
            'body' => ['required', 'string', 'min:20', 'max:1000'],
            'rating' => ['nullable', 'integer', 'between:1,'.Testimonial::MAX_RATING],
            'consent' => ['accepted'],
            self::HONEYPOT_FIELD => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'name',
            'body' => 'experience',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please confirm we may show your words and name on our website.',
            'body.min' => 'Please write at least a sentence or two (:min characters).',
        ];
    }

    /**
     * Whether the hidden field was filled in (an automated submission).
     *
     * @return bool
     */
    public function isLikelySpam(): bool
    {
        return filled($this->input(self::HONEYPOT_FIELD));
    }
}
