@props(['testimonial'])
{{-- One approved testimonial. Callers must only pass Testimonial::approved() records. --}}
<li class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <figure class="flex h-full flex-col">
        @if ($testimonial->rating)
            <p class="text-lg text-accent-600">
                <span aria-hidden="true">{{ str_repeat('★', $testimonial->rating) }}{{ str_repeat('☆', \App\Models\Testimonial::MAX_RATING - $testimonial->rating) }}</span>
                <span class="sr-only">Rated {{ $testimonial->rating }} out of {{ \App\Models\Testimonial::MAX_RATING }}</span>
            </p>
        @endif
        <blockquote class="mt-2 flex-1 whitespace-pre-line text-slate-700">{{ $testimonial->body }}</blockquote>
        <figcaption class="mt-4 font-semibold text-slate-900">{{ $testimonial->customer_name }}</figcaption>
    </figure>
</li>
