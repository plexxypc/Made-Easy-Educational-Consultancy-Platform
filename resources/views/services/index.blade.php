<x-public-layout
    title="Our Services"
    description="All Made Easy services with prices: WAEC, NECO, GCE and NABTEB registration, JAMB services, admission guidance, result checkers and document services."
>
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">Our Services</h1>
        <p class="mt-3 max-w-2xl text-ink/80">Choose a category to see prices and send a request. All prices are in Naira (₦).</p>
        <p class="mt-2 max-w-2xl text-ink/80">Looking for Maths lessons? See our <a href="{{ route('maths-tutoring') }}" class="font-semibold text-accent-700 underline">online Mathematics tutorials</a>.</p>

        @forelse ($categories as $category)
            <section class="mt-10" aria-labelledby="category-{{ $category->slug }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 border-ink bg-accent-100 text-ink">
                            <x-public.category-icon :slug="$category->slug" />
                        </span>
                        <h2 id="category-{{ $category->slug }}" class="text-2xl font-bold text-ink">{{ $category->name }}</h2>
                    </div>
                    <a href="{{ $category->publicUrl() }}" class="inline-flex min-h-[44px] items-center text-sm font-semibold text-accent-700 hover:underline">
                        View all<span class="sr-only"> {{ $category->name }}</span> &rarr;
                    </a>
                </div>
                @if (filled($category->description))
                    <p class="mt-2 text-sm text-ink/70">{{ $category->description }}</p>
                @endif

                <ul class="mt-4 divide-y divide-ink/10 overflow-hidden rounded-2xl border-2 border-ink bg-white">
                    @foreach ($category->activeServices as $service)
                        <li>
                            <a
                                href="{{ $category->isResultCheckers() ? $category->publicUrl() : route('services.show', ['category' => $category, 'service' => $service]) }}"
                                class="flex min-h-[56px] items-center justify-between gap-4 px-5 py-3 hover:bg-accent-50"
                            >
                                <span class="font-medium text-ink">{{ $service->name }}</span>
                                <span class="shrink-0 font-semibold text-ink">{{ $service->priceLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="mt-8 text-ink/70">No services are available right now. Please <a href="{{ route('contact') }}" class="font-semibold text-accent-700 underline">contact us</a>.</p>
        @endforelse
    </section>
</x-public-layout>
