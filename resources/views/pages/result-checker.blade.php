{{-- PLACEHOLDER: the "Buy Result Checker" store (PDF section 5) is built in Prompt 7. Don't list products or prices here until then. --}}
<x-public-layout title="Result Checker">
    <section class="mx-auto max-w-3xl px-4 py-14 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Buy Result Checker</h1>
        <p class="mt-4 text-lg text-slate-700">
            Online purchase of WAEC, NECO, NABTEB and GCE result checkers is coming soon.
            In the meantime, contact us and we'll help you get one.
        </p>
        <div class="mt-8">
            <x-public.button :href="route('contact')">Contact Us</x-public.button>
        </div>
    </section>
</x-public-layout>
