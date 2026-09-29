@php($nav_items = \App\Support\PublicNavigation::items(request()))

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="{{ route('home') }}" class="flex min-h-[48px] items-center gap-3" aria-label="{{ config('madeeasy.contact.business_name') }}, home">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-700 text-lg font-bold text-white" aria-hidden="true">ME</span>
            <span class="leading-tight">
                <span class="block text-base font-bold text-slate-900">Made Easy</span>
                <span class="block text-xs text-slate-500">Educational Consultancy</span>
            </span>
        </a>

        {{-- Desktop navigation --}}
        <nav aria-label="Main" class="hidden xl:block">
            <ul class="flex items-center gap-1">
                @foreach ($nav_items as $item)
                    <li>
                        <a
                            href="{{ $item['url'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @class([
                                'flex min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold transition',
                                'bg-brand-50 text-brand-800' => $item['active'],
                                'text-slate-700 hover:bg-slate-100 hover:text-slate-900' => ! $item['active'],
                            ])
                        >{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-2">
            @auth
                @role('staff', 'admin')
                    <a href="{{ url('/admin') }}" class="hidden min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:flex">Back office</a>
                @endrole
                <a href="{{ route('my-requests') }}" class="hidden min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:flex">My Requests</a>
                <a href="{{ route('dashboard') }}" class="hidden min-h-[44px] items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-100 sm:flex">My account</a>
            @else
                <a href="{{ route('orders.track') }}" class="hidden min-h-[44px] items-center rounded-lg px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:flex">Track request</a>
                <a href="{{ route('login') }}" class="hidden min-h-[44px] items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-100 sm:flex">Log in</a>
            @endauth

            {{-- Mobile/tablet navigation: native <details>, works without JavaScript --}}
            <details class="group relative xl:hidden">
                <summary class="flex min-h-[48px] cursor-pointer list-none items-center gap-2 rounded-lg border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path class="group-open:hidden" stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path class="hidden group-open:block" stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                    Menu
                </summary>
                <nav aria-label="Main" class="absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                    <ul class="flex flex-col">
                        @foreach ($nav_items as $item)
                            <li>
                                <a
                                    href="{{ $item['url'] }}"
                                    @if ($item['active']) aria-current="page" @endif
                                    @class([
                                        'flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold',
                                        'bg-brand-50 text-brand-800' => $item['active'],
                                        'text-slate-800 hover:bg-slate-100' => ! $item['active'],
                                    ])
                                >{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                        <li class="mt-1 border-t border-slate-100 pt-1 sm:hidden">
                            @auth
                                <a href="{{ route('my-requests') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-slate-800 hover:bg-slate-100">My Requests</a>
                                <a href="{{ route('dashboard') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-slate-800 hover:bg-slate-100">My account</a>
                            @else
                                <a href="{{ route('orders.track') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-slate-800 hover:bg-slate-100">Track your request</a>
                                <a href="{{ route('login') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-slate-800 hover:bg-slate-100">Log in</a>
                            @endauth
                        </li>
                    </ul>
                </nav>
            </details>
        </div>
    </div>
</header>
