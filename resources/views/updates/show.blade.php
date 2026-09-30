{{-- One published post. <title> and <meta name="description"> come from the post's SEO fields (Post::seoTitle / seoDescription). --}}
<x-public-layout
    :meta-title="$post->seoTitle()"
    :description="$post->seoDescription()"
    :canonical-url="$post->publicUrl()"
    :og-image="$post->featuredImageUrl()"
>
    <article class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('updates') }}" class="hover:underline">Updates</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $post->category->publicUrl() }}" class="hover:underline">{{ $post->category->name }}</a>
        </nav>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $post->title }}</h1>
        <p class="mt-3 text-sm text-slate-500">
            Published <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->publishedOnLabel() }}</time>
            in <a href="{{ $post->category->publicUrl() }}" class="font-semibold text-brand-700 hover:underline">{{ $post->category->name }}</a>
        </p>

        @if ($post->featuredImageUrl())
            <img src="{{ $post->featuredImageUrl() }}" alt="" class="mt-6 aspect-[16/9] w-full rounded-2xl object-cover">
        @endif

        {{-- Sanitised by Filament's rich content renderer (no scripts, event handlers or unsafe URLs). --}}
        <div class="post-body mt-8">
            {!! $post->bodyHtml() !!}
        </div>

        <div class="mt-10 flex flex-col gap-4 rounded-2xl border border-brand-200 bg-brand-50 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-lg font-semibold text-slate-900">Need help with this?</p>
                <p class="text-sm text-slate-700">We handle registrations, admission guidance and documents for you.</p>
            </div>
            <x-public.button :href="route('services.index')">Explore Our Services</x-public.button>
        </div>
    </article>

    @if ($related_posts->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 pb-4 sm:px-6" aria-labelledby="related-heading">
            <h2 id="related-heading" class="text-2xl font-bold text-slate-900">More {{ $post->category->name }}</h2>
            <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related_posts as $related_post)
                    <x-public.post-card :post="$related_post" heading="h3" />
                @endforeach
            </ul>
        </section>
    @endif
</x-public-layout>
