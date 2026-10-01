@php($nav_items = \App\Support\PublicNavigation::items(request()))

<header class="sticky top-0 z-40 border-b-2 border-ink bg-surface/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
        {{-- Placeholder mark until Made Easy's own logo is supplied. --}}
        <a href="{{ route('home') }}" class="flex min-h-[48px] items-center gap-3 rounded-xl" aria-label="{{ config('madeeasy.contact.business_name') }}, home">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl border-2 border-ink bg-accent font-display text-lg font-bold text-ink" aria-hidden="true">ME</span>
            <span class="leading-tight">
                <span class="block font-display text-base font-bold text-ink">Made Easy</span>
                <span class="block text-xs text-ink/60">Educational Consultancy</span>
            </span>
        </a>

        {{-- Desktop navigation. Sub-links open on hover or keyboard focus (CSS only); the parent link still works on its own. --}}
        <nav aria-label="Main" class="hidden xl:block">
            <ul class="flex items-center gap-1">
                @foreach ($nav_items as $item)
                    <li @class(['group relative' => ! empty($item['children'])])>
                        <a
                            href="{{ $item['url'] }}"
                            @if ($item['active'] && empty($item['children'])) aria-current="page" @endif
                            @class([
                                'flex min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold',
                                'bg-accent-100 text-ink' => $item['active'],
                                'text-ink/80 hover:bg-ink/5 hover:text-ink' => ! $item['active'],
                            ])
                        >
                            {{ $item['label'] }}
                            @if (! empty($item['children']))
                                <svg class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            @endif
                        </a>
                        @if (! empty($item['children']))
                            <ul class="invisible absolute left-0 top-full z-50 w-64 rounded-xl border-2 border-ink bg-white p-2 opacity-0 shadow-pop group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a
                                            href="{{ $child['url'] }}"
                                            @if ($child['active']) aria-current="page" @endif
                                            @class([
                                                'flex min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold',
                                                'bg-accent-100 text-ink' => $child['active'],
                                                'text-ink/80 hover:bg-ink/5' => ! $child['active'],
                                            ])
                                        >{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="flex items-center gap-2">
            @auth
                @role('staff', 'admin')
                    <a href="{{ url('/admin') }}" class="hidden min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold text-ink/80 hover:bg-ink/5 sm:flex">Back office</a>
                @endrole
                <a href="{{ route('my-requests') }}" class="hidden min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold text-ink/80 hover:bg-ink/5 sm:flex">My Requests</a>
                <a href="{{ route('profile') }}" class="hidden min-h-[44px] items-center whitespace-nowrap rounded-lg border-2 border-ink bg-white px-4 text-sm font-semibold text-ink hover:bg-surface sm:flex">My account</a>
                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                    @csrf
                    <button type="submit" class="flex min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold text-ink/80 hover:bg-ink/5 hover:text-ink">Log out</button>
                </form>
            @else
                <a href="{{ route('orders.track') }}" class="hidden min-h-[44px] items-center whitespace-nowrap rounded-lg px-3 text-sm font-semibold text-ink/80 hover:bg-ink/5 sm:flex">Track request</a>
                <a href="{{ route('login') }}" class="hidden min-h-[44px] items-center whitespace-nowrap rounded-lg border-2 border-ink bg-white px-4 text-sm font-semibold text-ink hover:bg-surface sm:flex">Log in</a>
            @endauth

            {{-- Mobile/tablet navigation: native <details>, works without JavaScript --}}
            <details class="group relative xl:hidden">
                <summary class="flex min-h-[48px] cursor-pointer list-none items-center gap-2 rounded-lg border-2 border-ink bg-white px-4 text-sm font-semibold text-ink hover:bg-surface [&::-webkit-details-marker]:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path class="group-open:hidden" stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                        <path class="hidden group-open:block" stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                    Menu
                </summary>
                <nav aria-label="Main" class="absolute right-0 mt-2 max-h-[calc(100vh-6rem)] w-72 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-2xl border-2 border-ink bg-white p-2 shadow-pop">
                    <ul class="flex flex-col">
                        @foreach ($nav_items as $item)
                            @foreach (empty($item['children']) ? [$item] : $item['children'] as $link)
                                <li>
                                    <a
                                        href="{{ $link['url'] }}"
                                        @if ($link['active']) aria-current="page" @endif
                                        @class([
                                            'flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold',
                                            'bg-accent-100 text-ink' => $link['active'],
                                            'text-ink hover:bg-ink/5' => ! $link['active'],
                                        ])
                                    >{{ empty($item['children']) ? $link['label'] : $item['label'].': '.$link['label'] }}</a>
                                </li>
                            @endforeach
                        @endforeach
                        <li class="mt-1 border-t border-ink/10 pt-1 sm:hidden">
                            @auth
                                <a href="{{ route('my-requests') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-ink hover:bg-ink/5">My Requests</a>
                                <a href="{{ route('profile') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-ink hover:bg-ink/5">My account</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex min-h-[48px] w-full items-center rounded-xl px-4 text-left text-base font-semibold text-ink hover:bg-ink/5">Log out</button>
                                </form>
                            @else
                                <a href="{{ route('orders.track') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-ink hover:bg-ink/5">Track your request</a>
                                <a href="{{ route('login') }}" class="flex min-h-[48px] items-center rounded-xl px-4 text-base font-semibold text-ink hover:bg-ink/5">Log in</a>
                            @endauth
                        </li>
                    </ul>
                </nav>
            </details>
        </div>
    </div>
</header>
