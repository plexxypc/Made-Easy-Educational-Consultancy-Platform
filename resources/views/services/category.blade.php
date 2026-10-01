<x-public-layout
    :title="$category->name"
    :description="filled($category->description) ? $category->description : $category->name.' from Made Easy Educational Consultancy: see prices and send your request online.'"
    :whatsapp-message="\App\Support\WhatsApp::category_message($category->name)"
>
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-ink/60">
            <a href="{{ route('services.index') }}" class="hover:underline">Services</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $category->name }}</span>
        </nav>

        <div class="mt-4 flex items-start gap-4">
            <span class="hidden h-14 w-14 shrink-0 items-center justify-center rounded-2xl border-2 border-ink bg-accent-100 text-ink sm:flex">
                <x-public.category-icon :slug="$category->slug" class="h-8 w-8" />
            </span>
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">{{ $category->name }}</h1>
                @if (filled($category->description))
                    <p class="mt-3 max-w-2xl text-ink/80">{{ $category->description }}</p>
                @endif
            </div>
        </div>

        {{-- PDF section 3: a prominent "I Need Admission Guidance" button on the admission page. --}}
        @if ($show_admission_guidance)
            <div class="mt-6 flex flex-col gap-4 rounded-2xl border-2 border-ink bg-highlight p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-display text-lg font-bold text-ink">Not sure where to start?</p>
                    <p class="text-sm text-ink/85">Tell us your JAMB score, O'Level results and preferred course, and we'll guide you.</p>
                </div>
                <x-public.button :href="$admission_guidance_url" variant="dark">I Need Admission Guidance</x-public.button>
            </div>
            <p class="mt-4 text-ink/80">
                Checking your chances first?
                <a href="{{ route('admission.search') }}" class="font-semibold text-accent-700 underline">Search courses and cut-off marks</a>
                by JAMB score, O'Level credits and state.
            </p>
        @endif

        @if ($services->isEmpty())
            <x-public.empty-state title="Services in this category are coming soon." class="mt-8 border-dashed">
                Need help now? <a href="{{ route('contact') }}" class="font-semibold text-accent-700 underline">Contact us</a>.
            </x-public.empty-state>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-public.service-card :service="$service" :category="$category" />
                @endforeach
            </ul>
        @endif
    </section>
</x-public-layout>
