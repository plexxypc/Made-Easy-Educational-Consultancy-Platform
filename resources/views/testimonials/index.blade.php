{{-- Approved testimonials only ($testimonials: paginator from Testimonial::approved()). --}}
<x-public-layout title="Testimonials" description="What students and parents say about working with Made Easy Educational Consultancy.">
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">What our customers say</h1>
        <p class="mt-3 max-w-2xl text-lg text-slate-700">Shared by students and parents after we completed their requests.</p>

        @if ($testimonials->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <p class="text-lg font-semibold text-slate-900">No testimonials yet.</p>
                <p class="mt-2 text-slate-600">Customers can share their experience from a completed request.</p>
            </div>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <x-public.testimonial-card :testimonial="$testimonial" />
                @endforeach
            </ul>
            <div class="mt-6">{{ $testimonials->links() }}</div>
        @endif
    </section>
</x-public-layout>
