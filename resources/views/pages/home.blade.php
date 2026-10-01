<x-public-layout
    meta-title="Made Easy Educational Consultancy | WAEC, NECO, JAMB & Admission Services"
    description="WAEC and NECO registration, JAMB services, admission guidance, result checker PINs and certificate processing. Request online and track your progress."
>
    {{-- Stats: only real figures. Counts come from the live categories below (active services only). --}}
    @php($category_count = $categories->count())
    @php($service_count = (int) $categories->sum('active_services_count'))

    {{-- Hero: headline and description verbatim from the requirements PDF, section 1. --}}
    <section class="border-b-2 border-ink" aria-labelledby="hero-heading">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-10 sm:px-6 sm:py-16 lg:grid-cols-[1.15fr_0.85fr]">
            <div class="motion-safe:animate-rise-in">
                <ul class="flex flex-wrap gap-2" aria-label="What we help with">
                    @foreach (['Exam registration', 'Admission help', 'Result checkers', 'Documents'] as $topic)
                        <li class="rounded-full border-2 border-ink bg-white px-3 py-1 text-sm font-semibold">{{ $topic }}</li>
                    @endforeach
                </ul>

                <h1 id="hero-heading" class="mt-6 max-w-3xl text-4xl font-bold leading-[1.1] tracking-tight text-ink sm:text-5xl lg:text-6xl">
                    Making Your Educational Journey Easier
                </h1>
                <span class="mt-5 block h-2 w-24 rounded-full bg-accent" aria-hidden="true"></span>
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink/80">
                    Made Easy Educational Consultancy provides reliable educational and documentation services,
                    including WAEC and NECO registration, admission guidance, result checking, certificate
                    processing, JAMB services, and other essential educational support.
                </p>

                <div class="mt-8 grid gap-3 sm:flex sm:flex-wrap">
                    <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
                    <x-public.button :href="$admission_guidance_url" variant="dark">Get Admission Guidance</x-public.button>
                    <x-public.button :href="route('contact')" variant="secondary">Contact Us</x-public.button>
                    {{-- Standalone payment arrives with Paystack (Prompt 6). Until then this goes to the catalogue. --}}
                    <x-public.button :href="route('services.index')" variant="secondary">Make Payment</x-public.button>
                </div>

                {{-- PDF section 16: the trust figure from Site settings is hidden until an admin enters a confirmed one. --}}
                @if (filled($students_assisted) || $service_count > 0)
                    <dl class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Our track record">
                        @if (filled($students_assisted))
                            <x-public.stat :value="$students_assisted" :label="$students_assisted_label" class="col-span-2 sm:col-span-1" />
                        @endif
                        @if ($service_count > 0)
                            <x-public.stat :value="$service_count" :label="trans_choice('service available online|services available online', $service_count)" />
                            <x-public.stat :value="$category_count" :label="trans_choice('service category|service categories', $category_count)" />
                        @endif
                    </dl>
                @endif
            </div>

            <div class="relative mx-auto w-full max-w-sm motion-safe:animate-rise-in motion-safe:[animation-delay:120ms] lg:max-w-none">
                <div class="absolute inset-x-4 bottom-0 top-8 rounded-[2.5rem] border-2 border-ink bg-highlight" aria-hidden="true"></div>
                <x-public.illustration name="hero-requests" eager fetchpriority="high" class="relative h-auto w-full px-6 pb-6" />
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16" aria-labelledby="how-heading">
        <h2 id="how-heading" class="text-3xl font-bold text-ink">How it works</h2>
        <ol class="mt-6 grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['title' => 'Choose a service', 'body' => 'Browse our services and pick what you need. Prices are shown up front, or marked "Price on request".'],
                ['title' => 'Send your details', 'body' => 'Fill in a short form and upload any documents we need. No account required.'],
                ['title' => 'We get to work', 'body' => 'You get a reference number straight away. Our team contacts you with the next steps, including payment.'],
            ] as $step)
                <li class="rounded-2xl border-2 border-ink bg-white p-5">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-ink font-display text-lg font-bold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                    <h3 class="mt-4 text-xl font-bold text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-ink/70">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Services overview: live from the database (categories with at least one active service), as a swipeable row. --}}
    <section class="mx-auto max-w-6xl px-4 pb-4 sm:px-6" aria-labelledby="services-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="services-heading" class="text-3xl font-bold text-ink">Our services</h2>
            <a href="{{ route('services.index') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent-700 hover:underline">All services &rarr;</a>
        </div>

        @if ($categories->isEmpty())
            <p class="mt-4 text-ink/70">Our services will be listed here soon. <a href="{{ route('contact') }}" class="font-semibold text-accent-700 underline">Contact us</a> in the meantime.</p>
        @else
            <ul class="carousel -mx-4 mt-6 scroll-px-4 px-4 sm:mx-0 sm:scroll-px-0 sm:px-0" aria-label="Service categories">
                @foreach ($categories as $category)
                    <li class="w-[82%] sm:w-[calc(50%-0.5rem)] lg:w-[calc((100%-2rem)/3)]">
                        <a href="{{ $category->publicUrl() }}" class="flex h-full flex-col rounded-2xl border-2 border-ink bg-white p-5 shadow-pop hover:bg-accent-50">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl border-2 border-ink bg-accent-100 text-ink">
                                <x-public.category-icon :slug="$category->slug" class="h-7 w-7" />
                            </span>
                            <span class="mt-4 font-display text-xl font-bold text-ink">{{ $category->name }}</span>
                            @if (filled($category->description))
                                <span class="mt-2 line-clamp-3 text-sm text-ink/70">{{ $category->description }}</span>
                            @endif
                            <span class="mt-auto pt-4 text-sm font-semibold text-accent-700">
                                {{ trans_choice(':count service|:count services', $category->active_services_count) }} &rarr;
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($category_count > 1)
                <p @class(['text-sm text-ink/60', 'sm:hidden' => $category_count <= 2, 'lg:hidden' => $category_count <= 3])>
                    Swipe or scroll sideways to see every category.
                </p>
            @endif
        @endif
    </section>

    {{-- Latest Updates: the three newest published posts. Hidden until something is published. --}}
    @if ($latest_posts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16" aria-labelledby="latest-updates-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="latest-updates-heading" class="text-3xl font-bold text-ink">Latest Updates</h2>
                <a href="{{ route('updates') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent-700 hover:underline">All updates &rarr;</a>
            </div>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($latest_posts as $post)
                    <x-public.post-card :post="$post" heading="h3" />
                @endforeach
            </ul>
        </section>
    @endif

    {{-- PDF section 12: Mathematics tutorials promo. Arranged on WhatsApp; the page explains how. --}}
    <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6" aria-labelledby="maths-heading">
        <div class="flex flex-col gap-4 rounded-2xl border-2 border-ink bg-secondary p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
            <div>
                <h2 id="maths-heading" class="text-2xl font-bold text-ink">Online Mathematics tutorials</h2>
                <p class="mt-1 text-ink/85">Lessons for WAEC, NECO, JAMB and school exams, arranged with us directly on WhatsApp.</p>
            </div>
            <x-public.button :href="route('maths-tutoring')" variant="dark">Find out more</x-public.button>
        </div>
    </section>

    {{-- PDF section 15: approved testimonials only. Hidden until one is approved. --}}
    @if ($testimonials->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16" aria-labelledby="testimonials-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="testimonials-heading" class="text-3xl font-bold text-ink">What our customers say</h2>
                <a href="{{ route('testimonials') }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent-700 hover:underline">All testimonials &rarr;</a>
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
            <h2 id="faq-heading" class="text-3xl font-bold text-ink">Frequently asked questions</h2>
            <div class="mt-6">
                @include('partials.public.faq-list', ['faqs' => $faqs, 'heading_tag' => 'h3'])
            </div>
            @if ($has_more_faqs)
                <p class="mt-4"><a href="{{ route('faq') }}" class="font-semibold text-accent-700 hover:underline">More questions &rarr;</a></p>
            @endif
        </section>
    @endif
</x-public-layout>
