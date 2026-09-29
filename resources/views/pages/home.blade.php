<x-public-layout>
    {{-- Hero: headline and description verbatim from the requirements PDF, section 1. --}}
    <section class="bg-gradient-to-b from-brand-50 to-slate-50">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-20">
            <h1 class="max-w-3xl text-3xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
                Making Your Educational Journey Easier
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-slate-700">
                Made Easy Educational Consultancy provides reliable educational and documentation services,
                including WAEC and NECO registration, admission guidance, result checking, certificate
                processing, JAMB services, and other essential educational support.
            </p>

            <div class="mt-8 grid gap-3 sm:flex sm:flex-wrap">
                <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
                <x-public.button :href="$admission_guidance_url" variant="accent">Get Admission Guidance</x-public.button>
                <x-public.button :href="route('contact')" variant="secondary">Contact Us</x-public.button>
                {{-- Standalone payment arrives with Paystack (Prompt 6). Until then this goes to the catalogue. --}}
                <x-public.button :href="route('services.index')" variant="secondary">Make Payment</x-public.button>
            </div>
        </div>
    </section>

    {{-- PLACEHOLDER (PDF section 16): trust indicator, e.g. "100+ Students Assisted With Admission". Needs a client-confirmed figure. --}}

    {{-- How it works --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h2 class="text-2xl font-bold text-slate-900">How it works</h2>
        <ol class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['title' => 'Choose a service', 'body' => 'Browse our services and pick what you need. Prices are shown up front, or marked "Price on request".'],
                ['title' => 'Send your details', 'body' => 'Fill in a short form and upload any documents we need. No account required.'],
                ['title' => 'We get to work', 'body' => 'You get a reference number straight away. Our team contacts you with the next steps, including payment.'],
            ] as $step)
                <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-700 text-base font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Services overview: live from the database (categories with at least one active service). --}}
    <section class="mx-auto max-w-6xl px-4 py-4 sm:px-6">
        <h2 class="text-2xl font-bold text-slate-900">Our services</h2>

        @if ($categories->isEmpty())
            <p class="mt-4 text-slate-600">Our services will be listed here soon. <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a> in the meantime.</p>
        @else
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ $category->publicUrl() }}" class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow">
                            <span class="text-lg font-semibold text-slate-900">{{ $category->name }}</span>
                            @if (filled($category->description))
                                <span class="mt-2 text-sm text-slate-600">{{ $category->description }}</span>
                            @endif
                            <span class="mt-4 text-sm font-semibold text-brand-700">
                                {{ trans_choice(':count service|:count services', $category->active_services_count) }} &rarr;
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- PLACEHOLDER (PDF section 12): Mathematics online tutorials promo with WhatsApp contact. Later prompt. --}}

    {{-- PLACEHOLDER (PDF section 15): testimonials. Later prompt. --}}

    {{-- PLACEHOLDER (PDF section 13): FAQ. Later prompt. --}}
</x-public-layout>
