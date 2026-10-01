{{-- Body text is editable by admins (Site settings > About Us page text). $paragraphs: plain-text paragraphs. --}}
<x-public-layout
    title="About Us"
    description="Made Easy Educational Consultancy helps students and parents with exam registration, result checking, admission guidance and educational documents."
>
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">About {{ config('madeeasy.contact.business_name') }}</h1>

        <div class="mt-6 space-y-4 text-lg leading-relaxed text-slate-700">
            @foreach ($paragraphs as $paragraph)
                <p class="whitespace-pre-line">{{ $paragraph }}</p>
            @endforeach
        </div>

        <div class="mt-8 grid gap-3 sm:flex">
            <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
            <x-public.button :href="route('contact')" variant="secondary">Contact Us</x-public.button>
        </div>
    </section>
</x-public-layout>
