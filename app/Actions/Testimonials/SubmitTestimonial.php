<?php

namespace App\Actions\Testimonials;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Testimonial;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * Save a customer's testimonial for their order, always as Pending: nothing
 * a customer writes reaches the public site until staff approve it.
 *
 * Rules: the order must be completed, and each order gets one testimonial
 * (the unique order_id index settles a double submit).
 */
class SubmitTestimonial
{
    /**
     * @param  Order  $order
     * @param  array{customer_name: string, body: string, rating?: int|string|null}  $data
     * @return Testimonial
     *
     * @throws BusinessRuleException If the order doesn't (or no longer) accept one.
     */
    public function handle(Order $order, array $data): Testimonial
    {
        if (! $order->acceptsTestimonial()) {
            throw new BusinessRuleException('This request can no longer take a testimonial.');
        }

        $testimonial = new Testimonial([
            'customer_name' => Str::squish($data['customer_name']),
            'body' => trim($data['body']),
            'rating' => filled($data['rating'] ?? null) ? (int) $data['rating'] : null,
        ]);
        $testimonial->order()->associate($order);

        try {
            $testimonial->save();
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException('You have already shared your experience for this request. Thank you!');
        }

        return $testimonial;
    }
}
