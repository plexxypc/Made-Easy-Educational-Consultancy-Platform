@props(['testimonial'])
{{-- One approved testimonial. Callers must only pass Testimonial::approved() records. --}}
<li class="flex h-full flex-col rounded-2xl border-2 border-ink bg-white p-5">
    <figure class="flex h-full flex-col">
        <span class="font-display text-5xl font-bold leading-none text-accent" aria-hidden="true">&ldquo;</span>
        @if ($testimonial->rating)
            <p class="text-lg text-accent-700">
                <span aria-hidden="true">{{ str_repeat('★', $testimonial->rating) }}{{ str_repeat('☆', \App\Models\Testimonial::MAX_RATING - $testimonial->rating) }}</span>
                <span class="sr-only">Rated {{ $testimonial->rating }} out of {{ \App\Models\Testimonial::MAX_RATING }}</span>
            </p>
        @endif
        <blockquote class="mt-2 flex-1 whitespace-pre-line text-ink/85">{{ $testimonial->body }}</blockquote>
        <figcaption class="mt-4 border-t border-ink/10 pt-3 font-semibold text-ink">{{ $testimonial->customer_name }}</figcaption>
    </figure>
</li>
