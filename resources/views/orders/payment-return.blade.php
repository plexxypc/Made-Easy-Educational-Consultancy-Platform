<x-public-layout title="Confirming your payment" :refresh_url="$refresh_url">
    <section class="mx-auto max-w-xl px-4 py-14 sm:px-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-10" role="status">
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Reference {{ $order->display_reference }}</p>

            @if ($refresh_url)
                <h1 class="mt-3 text-2xl font-extrabold text-slate-900">
                    {{ $paystack_says_paid ? 'Payment received, confirming…' : 'Checking your payment…' }}
                </h1>
                <p class="mt-4 text-slate-700">
                    {{ $paystack_says_paid
                        ? "Paystack has told us your payment went through. We're confirming it now; this usually takes a few seconds."
                        : "We're waiting for Paystack to confirm your payment." }}
                </p>
                <p class="mt-3 text-sm text-slate-500">This page refreshes by itself. Please don't pay again.</p>
                <a href="{{ $refresh_url }}" class="mt-6 inline-flex min-h-[48px] items-center justify-center rounded-xl border border-slate-300 px-6 font-semibold text-slate-800 hover:bg-slate-100">Check again now</a>
            @else
                <h1 class="mt-3 text-2xl font-extrabold text-slate-900">Your payment is still being confirmed</h1>
                <p class="mt-4 text-slate-700">
                    This is taking longer than usual. Please don't pay again: once it's confirmed we'll email your receipt,
                    and your request page will show it as paid.
                </p>
                <div class="mt-6 grid gap-3 sm:flex sm:justify-center">
                    <x-public.button :href="route('orders.track.show', $order)">Go to your request</x-public.button>
                    <x-public.button :href="route('contact')" variant="secondary">Contact us</x-public.button>
                </div>
            @endif
        </div>
    </section>
</x-public-layout>
