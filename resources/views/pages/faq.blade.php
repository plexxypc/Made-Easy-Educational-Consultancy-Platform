{{-- Frequently asked questions (PDF section 13), managed in the back office (FAQ). $faqs: active, in order. --}}
<x-public-layout
    title="Frequently Asked Questions"
    description="Answers to common questions about WAEC, NECO and JAMB registration, admission guidance, result checker PINs, payments and tracking your request."
>
    @if ($faqs->isNotEmpty())
        <x-slot:head>
            <script type="application/ld+json">{!! \App\Models\Faq::structured_data($faqs) !!}</script>
        </x-slot:head>
    @endif

    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Frequently Asked Questions</h1>

        @if ($faqs->isEmpty())
            <p class="mt-6 text-lg text-slate-700">Questions and answers will appear here soon.</p>
        @else
            <div class="mt-8">
                @include('partials.public.faq-list', ['faqs' => $faqs, 'heading_tag' => 'h2'])
            </div>
        @endif

        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-lg font-semibold text-slate-900">Still have a question?</p>
            <p class="mt-1 text-slate-700">Send us a WhatsApp message or see our <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">contact details</a>.</p>
        </div>
    </section>
</x-public-layout>
