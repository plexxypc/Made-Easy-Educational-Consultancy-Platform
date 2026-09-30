{{-- Updates index (PDF section 10). $posts: published only, newest first. $current_category: filter, or null. --}}
<x-public-layout
    :title="$current_category ? $current_category->name.' | Admission Updates' : 'Admission Updates'"
    :description="$current_category
        ? 'The latest '.$current_category->name.' from Made Easy Educational Consultancy.'
        : 'JAMB news, WAEC and NECO updates, admission deadlines, Post-UTME announcements, cut-off marks and scholarships.'"
    :canonical-url="$current_category ? $current_category->publicUrl() : route('updates')"
>
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        @if ($current_category)
            <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
                <a href="{{ route('updates') }}" class="hover:underline">Updates</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $current_category->name }}</span>
            </nav>
        @endif

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">{{ $current_category?->name ?? 'Admission Updates' }}</h1>
        <p class="mt-3 max-w-2xl text-slate-700">
            JAMB news, WAEC and NECO updates, admission deadlines, Post-UTME announcements and more.
        </p>

        <nav class="mt-6 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Update categories">
            <ul class="flex gap-2 pb-2 sm:flex-wrap">
                <li>
                    <a href="{{ route('updates') }}"
                        @if (! $current_category) aria-current="page" @endif
                        @class([
                            'inline-flex min-h-[40px] items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold',
                            'border-brand-700 bg-brand-700 text-white' => ! $current_category,
                            'border-slate-300 bg-white text-slate-700 hover:border-brand-300' => $current_category,
                        ])>All updates</a>
                </li>
                @foreach ($categories as $category)
                    @php($is_current = $current_category?->is($category))
                    <li>
                        <a href="{{ $category->publicUrl() }}"
                            @if ($is_current) aria-current="page" @endif
                            @class([
                                'inline-flex min-h-[40px] items-center whitespace-nowrap rounded-full border px-4 text-sm font-semibold',
                                'border-brand-700 bg-brand-700 text-white' => $is_current,
                                'border-slate-300 bg-white text-slate-700 hover:border-brand-300' => ! $is_current,
                            ])>{{ $category->name }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if ($posts->isEmpty())
            <div class="mt-8 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <p class="text-lg font-semibold text-slate-900">
                    {{ $current_category ? 'No updates in this category yet.' : 'No updates have been published yet.' }}
                </p>
                <p class="mt-2 text-slate-600">
                    @if ($current_category)
                        <a href="{{ route('updates') }}" class="font-semibold text-brand-700 underline">See all updates</a>, or
                    @endif
                    <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">contact us</a> with any question.
                </p>
            </div>
        @else
            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-public.post-card :post="$post" />
                @endforeach
            </ul>

            <div class="mt-8">
                {{ $posts->links() }}
            </div>
        @endif
    </section>
</x-public-layout>
