<x-public-layout title="About Us">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">About {{ config('madeeasy.contact.business_name') }}</h1>

        {{-- CLIENT REVIEW: placeholder copy written from the requirements PDF. Replace with the client's own story, history and team details before go-live. --}}
        <div class="mt-6 space-y-4 text-lg leading-relaxed text-slate-700">
            <p>
                Made Easy Educational Consultancy helps students and parents through every step of their
                educational journey, from exam registration and result checking to admission and
                documentation.
            </p>
            <p>
                We handle WAEC, NECO, GCE and NABTEB registration, JAMB services, admission guidance for
                universities, polytechnics and colleges of education, and document services such as
                certificates, affidavits and verification.
            </p>
            <p>
                Our aim is simple: clear prices, honest advice, and a team that follows your request through
                until it's done.
            </p>
        </div>

        <div class="mt-8 grid gap-3 sm:flex">
            <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
            <x-public.button :href="route('contact')" variant="secondary">Contact Us</x-public.button>
        </div>
    </section>
</x-public-layout>
