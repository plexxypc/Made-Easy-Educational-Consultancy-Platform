@props([
    'post',
    'heading' => 'h2',
])
{{-- One published post in a list: image, category, date, title and summary. The whole card links to the post. --}}
<li>
    <article class="group relative flex h-full flex-col overflow-hidden rounded-2xl border-2 border-ink bg-white hover:bg-accent-50">
        @if ($post->featuredImageUrl())
            <img src="{{ $post->featuredImageUrl() }}" alt="" loading="lazy" class="aspect-[16/9] w-full border-b-2 border-ink object-cover">
        @endif
        <div class="flex flex-1 flex-col p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-accent-700">
                {{ $post->category->name }}
                <span class="text-ink/40" aria-hidden="true">·</span>
                <time datetime="{{ $post->published_at->toAtomString() }}" class="font-medium normal-case tracking-normal text-ink/60">{{ $post->publishedOnLabel() }}</time>
            </p>
            <{{ $heading }} class="mt-2 font-display text-lg font-bold text-ink">
                <a href="{{ $post->publicUrl() }}" class="after:absolute after:inset-0 group-hover:underline">{{ $post->title }}</a>
            </{{ $heading }}>
            <p class="mt-2 flex-1 text-sm text-ink/70">{{ $post->summary(160) }}</p>
            <span class="mt-4 text-sm font-semibold text-accent-700" aria-hidden="true">Read more &rarr;</span>
        </div>
    </article>
</li>
