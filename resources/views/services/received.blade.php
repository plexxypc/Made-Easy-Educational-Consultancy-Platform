<x-public-layout title="Request received">
    <section class="mx-auto max-w-2xl px-4 py-14 sm:px-6">
        <div class="rounded-2xl border border-emerald-200 bg-white p-6 text-center shadow-sm sm:p-10">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </span>

            <h1 class="mt-5 text-2xl font-extrabold text-slate-900 sm:text-3xl">Your request has been received.</h1>
            <p class="mt-4 text-lg text-slate-800">Reference <strong class="whitespace-nowrap">#{{ $order->id }}</strong>. We'll be in touch.</p>

            <p class="mt-4 text-slate-600">
                Service: <strong>{{ $order->service->name }}</strong><br>
                We've emailed you a confirmation. Please quote your reference whenever you contact us.
            </p>
            <p class="mt-3 text-sm text-slate-500">No payment has been taken.</p>

            <p class="mt-3 text-sm text-slate-600">
                You can check progress any time on <a href="{{ route('orders.track') }}" class="font-semibold text-brand-700 underline">Track your request</a>
                using this reference and your email or phone number.
            </p>

            <div class="mt-8 grid gap-3 sm:flex sm:justify-center">
                <x-public.button :href="route('orders.track')">Track your request</x-public.button>
                <x-public.button :href="route('services.index')" variant="secondary">Browse more services</x-public.button>
                <x-public.button :href="route('contact')" variant="secondary">Contact us</x-public.button>
            </div>
        </div>
    </section>
</x-public-layout>
