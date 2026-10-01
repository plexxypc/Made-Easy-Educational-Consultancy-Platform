{{--
    "Share your experience" for one completed order (TestimonialController::create).
    $order, $suggested_name ("Adaeze O.": first name + initial unless the customer changes it).
--}}
@php($input_classes = 'block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout title="Share your experience" :noindex="true">
    <section class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
        <a href="{{ route('orders.track.show', $order) }}" class="text-sm text-slate-500 hover:underline">&larr; Back to request {{ $order->display_reference }}</a>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">Share your experience</h1>
        <p class="mt-3 text-lg text-slate-700">
            How was your {{ $order->serviceLabel() }} with us? We read every message. With your permission, we may show it on our website after a quick review.
        </p>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-red-300 bg-red-50 p-4 text-red-800" role="alert">
                <p class="font-semibold">Please check the form:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('orders.testimonial.store', $order) }}" class="mt-8 space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @csrf

            <div>
                <label for="body" class="block text-base font-semibold text-slate-900">Your experience</label>
                <textarea id="body" name="body" rows="6" required minlength="20" maxlength="1000" class="{{ $input_classes }} mt-2">{{ old('body') }}</textarea>
                <p class="mt-1 text-sm text-slate-500">20 to 1,000 characters.</p>
            </div>

            <fieldset>
                <legend class="text-base font-semibold text-slate-900">Rating <span class="font-normal text-slate-500">(optional)</span></legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (range(\App\Models\Testimonial::MAX_RATING, 1) as $stars)
                        <label class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-xl border border-slate-300 px-4 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="rating" value="{{ $stars }}" @checked((string) old('rating') === (string) $stars) class="text-brand-700 focus:ring-brand-500">
                            <span><span aria-hidden="true">{{ str_repeat('★', $stars) }}</span><span class="sr-only">{{ $stars }} {{ \Illuminate\Support\Str::plural('star', $stars) }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div>
                <label for="customer_name" class="block text-base font-semibold text-slate-900">Name to show</label>
                <input id="customer_name" name="customer_name" type="text" required minlength="2" maxlength="80" value="{{ old('customer_name', $suggested_name) }}" class="{{ $input_classes }} mt-2 min-h-[48px]">
                <p class="mt-1 text-sm text-slate-500">We suggest your first name and initial. Change it if you prefer.</p>
            </div>

            {{-- Honeypot: hidden from people and screen readers; bots that fill it are dropped. --}}
            <div class="hidden" aria-hidden="true">
                <label for="{{ \App\Http\Requests\SubmitTestimonialRequest::HONEYPOT_FIELD }}">Leave this empty</label>
                <input id="{{ \App\Http\Requests\SubmitTestimonialRequest::HONEYPOT_FIELD }}" name="{{ \App\Http\Requests\SubmitTestimonialRequest::HONEYPOT_FIELD }}" type="text" tabindex="-1" autocomplete="off">
            </div>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-1 h-5 w-5 rounded border-slate-300 text-brand-700 focus:ring-brand-500">
                <span class="text-slate-800">I agree that {{ config('madeeasy.contact.business_name') }} may show my words, rating and the name above on its website.</span>
            </label>

            <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                Send
            </button>
        </form>
    </section>
</x-public-layout>
