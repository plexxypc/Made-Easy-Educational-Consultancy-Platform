<x-public-layout
    meta-title="Made Easy Educational Consultancy | WAEC, NECO, JAMB & Admission Services"
    description="WAEC and NECO registration, JAMB services, admission guidance, result checker PINs and certificate processing. Request online and track your progress."
>
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

    {{-- PDF section 16: trust indicator from Site settings. Hidden until an admin enters a confirmed figure. --}}
    @if (filled($students_assisted))
        <section class="mx-auto max-w-6xl px-4 pt-10 sm:px-6" aria-label="Our track record">
            <p class="flex flex-col items-center rounded-2xl border border-brand-100 bg-white px-5 py-6 text-center shadow-sm sm:flex-row sm:justify-center sm:gap-4">
                <span class="text-4xl font-extrabold text-brand-700">{{ $students_assisted }}</span>
                <span class="mt-1 text-lg font-semibold text-slate-800 sm:mt-0">{{ $students_assisted_label }}</span>
            </p>
        </section>
    @endif

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

    {{-- Latest Updates: the three newest published posts. Hidden until something is published. --}}
    @if ($latest_posts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6" aria-labelledby="latest-updates-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="latest-updates-heading" class="text-2xl font-bold text-slate-900">Latest Updates</h2>
                <a href="{{ route('updates') }}" class="text-sm font-semibold text-brand-700 hover:underline">All updates &rarr;</a>
            </div>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest_posts as $post)
                    <x-public.post-card :post="$post" heading="h3" />
                @endforeach
            </ul>
        </section>
    @endif

    {{-- PDF section 12: Mathematics tutorials promo. Arranged on WhatsApp; the page explains how. --}}
    <section class="mx-auto max-w-6xl px-4 py-4 sm:px-6" aria-labelledby="maths-heading">
        <div class="flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-50 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="maths-heading" class="text-xl font-bold text-slate-900">Online Mathematics tutorials</h2>
                <p class="mt-1 text-slate-700">Lessons for WAEC, NECO, JAMB and school exams, arranged with us directly on WhatsApp.</p>
            </div>
            <x-public.button :href="route('maths-tutoring')">Find out more</x-public.button>
        </div>
    </section>

    {{-- PDF section 15: approved testimonials only. Hidden until one is approved. --}}
    @if ($testimonials->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6" aria-labelledby="testimonials-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="testimonials-heading" class="text-2xl font-bold text-slate-900">What our customers say</h2>
                <a href="{{ route('testimonials') }}" class="text-sm font-semibold text-brand-700 hover:underline">All testimonials &rarr;</a>
            </div>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-public.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </ul>
        </section>
    @endif

    {{-- PDF section 13: the first few FAQs. The FAQPage structured data is on /faq only, so it isn't duplicated. --}}
    @if ($faqs->isNotEmpty())
        <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6" aria-labelledby="faq-heading">
            <h2 id="faq-heading" class="text-2xl font-bold text-slate-900">Frequently asked questions</h2>
            <div class="mt-6">
                @include('partials.public.faq-list', ['faqs' => $faqs, 'heading_tag' => 'h3'])
            </div>
            @if ($has_more_faqs)
                <p class="mt-4"><a href="{{ route('faq') }}" class="font-semibold text-brand-700 hover:underline">More questions &rarr;</a></p>
            @endif
        </section>
    @endif
</x-public-layout>
