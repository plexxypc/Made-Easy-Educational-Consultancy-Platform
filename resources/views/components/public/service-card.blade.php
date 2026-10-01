@props([
    'service',
    'category',
])
@php($is_quote = $service->fulfilment_type === \App\Enums\FulfilmentType::Quote)
{{-- One service in a catalogue list: name, description, price and a clear action. --}}
<li class="flex flex-col rounded-2xl border-2 border-ink bg-white p-5">
    <h3 class="text-lg font-bold text-ink">{{ $service->name }}</h3>

    @if (filled($service->description))
        <p class="mt-2 text-sm text-ink/70">{{ $service->description }}</p>
    @endif

    <div class="mt-4 flex flex-1 flex-wrap items-end justify-between gap-3 border-t border-ink/10 pt-4">
        <p @class([
            'font-bold',
            'text-xl text-ink' => ! $is_quote,
            'text-base text-accent-800' => $is_quote,
        ])>{{ $service->priceLabel() }}</p>
        @if ($service->isConsultation())
            {{-- Consultations are booked on /book-consultation, not through the request form. --}}
            <a
                href="{{ route('book-consultation') }}"
                class="inline-flex min-h-[48px] items-center rounded-xl border-2 border-ink bg-accent px-5 text-sm font-semibold text-ink shadow-pop-sm hover:bg-accent-600"
            >
                Book a time<span class="sr-only">: {{ $service->name }}</span>
            </a>
        @else
            <a
                href="{{ route('services.show', ['category' => $category, 'service' => $service]) }}"
                class="inline-flex min-h-[48px] items-center rounded-xl border-2 border-ink bg-accent px-5 text-sm font-semibold text-ink shadow-pop-sm hover:bg-accent-600"
            >
                View &amp; request<span class="sr-only">: {{ $service->name }}</span>
            </a>
        @endif
    </div>
</li>
