{{-- PLACEHOLDER: the admission updates / blog (PDF section 10) is built in Prompt 10. --}}
<x-public-layout title="Updates">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Admission Updates</h1>
        <p class="mt-4 text-lg text-slate-700">
            JAMB news, WAEC and NECO updates, admission deadlines, Post-UTME announcements and more will
            be published here soon.
        </p>
        <div class="mt-8">
            <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
        </div>
    </section>
</x-public-layout>
