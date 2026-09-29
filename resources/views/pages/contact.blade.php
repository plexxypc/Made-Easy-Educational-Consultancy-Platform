{{-- All contact details come from config('madeeasy.contact'), set in .env. Don't hardcode them here. --}}
@php($contact = config('madeeasy.contact'))

<x-public-layout title="Contact">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Contact Us</h1>
        <p class="mt-3 text-lg text-slate-700">Call, send a WhatsApp message or email us. If you've already sent a request, please have your reference number ready.</p>

        <dl class="mt-8 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="px-5 py-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Business</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">{{ $contact['business_name'] }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Phone</dt>
                <dd class="mt-1">
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="inline-flex min-h-[44px] items-center text-lg font-semibold text-brand-700 hover:underline">{{ $contact['phone'] }}</a>
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">WhatsApp</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">{{ $contact['whatsapp'] }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1">
                    <a href="mailto:{{ $contact['email'] }}" class="inline-flex min-h-[44px] items-center break-all text-lg font-semibold text-brand-700 hover:underline">{{ $contact['email'] }}</a>
                </dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Office address</dt>
                <dd class="mt-1 text-lg text-slate-900">{{ $contact['address'] }}</dd>
            </div>
            @if (filled($contact['hours']))
                <div class="px-5 py-4">
                    <dt class="text-sm font-semibold uppercase tracking-wide text-slate-500">Opening hours</dt>
                    <dd class="mt-1 text-lg text-slate-900">{{ $contact['hours'] }}</dd>
                </div>
            @endif
        </dl>
    </section>
</x-public-layout>
