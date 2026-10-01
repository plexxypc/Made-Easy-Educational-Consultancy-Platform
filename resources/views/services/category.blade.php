<x-public-layout
    :title="$category->name"
    :description="filled($category->description) ? $category->description : $category->name.' from Made Easy Educational Consultancy: see prices and send your request online.'"
    :whatsapp-message="\App\Support\WhatsApp::category_message($category->name)"
>
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('services.index') }}" class="hover:underline">Services</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $category->name }}</span>
        </nav>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ $category->name }}</h1>
        @if (filled($category->description))
            <p class="mt-3 max-w-2xl text-slate-700">{{ $category->description }}</p>
        @endif

        {{-- PDF section 3: a prominent "I Need Admission Guidance" button on the admission page. --}}
        @if ($show_admission_guidance)
            <div class="mt-6 flex flex-col gap-4 rounded-2xl border border-accent-200 bg-accent-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-lg font-semibold text-slate-900">Not sure where to start?</p>
                    <p class="text-sm text-slate-700">Tell us your JAMB score, O'Level results and preferred course, and we'll guide you.</p>
                </div>
                <x-public.button :href="$admission_guidance_url" variant="accent">I Need Admission Guidance</x-public.button>
            </div>
            <p class="mt-4 text-slate-700">
                Checking your chances first?
                <a href="{{ route('admission.search') }}" class="font-semibold text-brand-700 underline">Search courses and cut-off marks</a>
                by JAMB score, O'Level credits and state.
            </p>
        @endif

        @if ($services->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <p class="text-lg font-semibold text-slate-900">Services in this category are coming soon.</p>
                <p class="mt-2 text-slate-600">Need help now? <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a>.</p>
            </div>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-public.service-card :service="$service" :category="$category" />
                @endforeach
            </ul>
        @endif
    </section>
</x-public-layout>
