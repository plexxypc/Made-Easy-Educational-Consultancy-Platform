@props([
    'post',
    'heading' => 'h2',
])
{{-- One published post in a list: image, category, date, title and summary. The whole card links to the post. --}}
<li>
    <article class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
        @if ($post->featuredImageUrl())
            <img src="{{ $post->featuredImageUrl() }}" alt="" loading="lazy" class="aspect-[16/9] w-full object-cover">
        @endif
        <div class="flex flex-1 flex-col p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">
                {{ $post->category->name }}
                <span class="text-slate-400" aria-hidden="true">·</span>
                <time datetime="{{ $post->published_at->toAtomString() }}" class="font-medium normal-case tracking-normal text-slate-500">{{ $post->publishedOnLabel() }}</time>
            </p>
            <{{ $heading }} class="mt-2 text-lg font-semibold text-slate-900 group-hover:text-brand-800">
                <a href="{{ $post->publicUrl() }}" class="after:absolute after:inset-0">{{ $post->title }}</a>
            </{{ $heading }}>
            <p class="mt-2 flex-1 text-sm text-slate-600">{{ $post->summary(160) }}</p>
            <span class="mt-4 text-sm font-semibold text-brand-700" aria-hidden="true">Read more &rarr;</span>
        </div>
    </article>
</li>
