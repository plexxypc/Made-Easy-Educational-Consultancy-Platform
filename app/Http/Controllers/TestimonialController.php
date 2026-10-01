<?php

namespace App\Http\Controllers;

use App\Actions\Testimonials\SubmitTestimonial;
use App\Exceptions\BusinessRuleException;
use App\Http\Requests\SubmitTestimonialRequest;
use App\Models\Order;
use App\Models\Testimonial;
use App\Support\TrackedOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Testimonials: the public list (approved only) and the "Share your
 * experience" form, which exists only under a completed order's tracking
 * page and needs the same access as that page (TrackedOrders::allows()).
 */
class TestimonialController extends Controller
{
    /**
     * Approved testimonials, newest approval first.
     *
     * @return View
     */
    public function index(): View
    {
        return view('testimonials.index', [
            'testimonials' => Testimonial::query()->approved()->paginate(12),
        ]);
    }

    /**
     * The form, for an order that accepts one.
     *
     * @param  Request  $request
     * @param  Order  $order
     * @return Response|RedirectResponse
     */
    public function create(Request $request, Order $order): Response|RedirectResponse
    {
        if (! TrackedOrders::allows($request, $order)) {
            return redirect()
                ->route('orders.track')
                ->withInput(['reference' => $order->reference])
                ->with('status', 'Enter the email or phone number you used, then choose "Share your experience" on your request.');
        }

        if (! $order->acceptsTestimonial()) {
            return redirect()->route('orders.track.show', $order);
        }

        return response()->view('testimonials.create', [
            'order' => $order->load('service'),
            'suggested_name' => self::suggested_name($order->customer_name),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Save as pending. Honeypot hits get the normal thank-you without being
     * saved, so a bot learns nothing.
     *
     * @param  SubmitTestimonialRequest  $request
     * @param  Order  $order
     * @param  SubmitTestimonial  $submit_testimonial
     * @return RedirectResponse
     */
    public function store(SubmitTestimonialRequest $request, Order $order, SubmitTestimonial $submit_testimonial): RedirectResponse
    {
        $thanks = redirect()
            ->route('orders.track.show', $order)
            ->with('status', 'Thank you for sharing your experience! We read every message, and approved ones appear on our website.');

        if ($request->isLikelySpam()) {
            Log::notice('Testimonial honeypot filled; submission dropped.', ['order_id' => $order->id, 'ip' => $request->ip()]);

            return $thanks;
        }

        try {
            $submit_testimonial->handle($order, $request->safe()->only(['customer_name', 'body', 'rating']));
        } catch (BusinessRuleException $exception) {
            return redirect()->route('orders.track.show', $order)->with('status', $exception->getMessage());
        }

        return $thanks;
    }

    /**
     * "Adaeze Okafor" -> "Adaeze O.": first name and initial by default, so
     * a full name isn't published unless the customer types it in.
     *
     * @param  string  $full_name
     * @return string
     */
    public static function suggested_name(string $full_name): string
    {
        $parts = preg_split('/\s+/u', trim($full_name)) ?: [];

        if (count($parts) < 2) {
            return trim($full_name);
        }

        return $parts[0].' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.';
    }
}
