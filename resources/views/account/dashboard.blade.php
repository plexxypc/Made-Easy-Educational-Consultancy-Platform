{{-- Customer account dashboard ("My Requests", PDF section 8). Data: CustomerDashboardController::show(). --}}
<x-public-layout title="My Requests">
    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-bold tracking-tight text-ink sm:text-4xl">My Requests</h1>
        <p class="mt-2 text-ink/70">Everything for your account: requests, payments, documents, consultations, PINs and messages.</p>

        {{-- Summary from $tab_counts (the same numbers as the tab badges). --}}
        <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Account summary">
            <x-public.stat compact :value="$tab_counts['requests']" :label="trans_choice('Request|Requests', $tab_counts['requests'])" />
            <x-public.stat compact :value="$tab_counts['payments']" :label="trans_choice('Successful payment|Successful payments', $tab_counts['payments'])" />
            <x-public.stat compact :value="$tab_counts['consultations']" :label="trans_choice('Consultation booked|Consultations booked', $tab_counts['consultations'])" />
            <x-public.stat compact :value="$tab_counts['documents']" :label="trans_choice('Document|Documents', $tab_counts['documents'])" />
        </dl>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('status') }}</div>
        @endif

        @error('claim')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">{{ $message }}</div>
        @enderror

        @if ($claimable_orders->isNotEmpty())
            {{-- Only for the account's own verified email (FindClaimableGuestOrders); nothing is linked until they accept. --}}
            <section class="mt-6 rounded-2xl border-2 border-ink bg-accent-50 p-5 sm:p-6" aria-labelledby="claim-heading">
                <h2 id="claim-heading" class="text-lg font-bold text-ink">
                    We found {{ $claimable_orders->count() }} {{ $claimable_orders->count() === 1 ? 'request' : 'requests' }} placed as a guest with your email — add {{ $claimable_orders->count() === 1 ? 'it' : 'them' }} to your account?
                </h2>
                <p class="mt-1 text-ink/80">These were sent without signing in, using {{ auth()->user()->email }}. Once added, they appear here and you won't need to look them up again.</p>
                <ul class="mt-3 divide-y divide-ink/10 rounded-xl border border-ink/20 bg-white">
                    @foreach ($claimable_orders as $claimable_order)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span>
                                <span class="block font-semibold text-ink">{{ $claimable_order->serviceLabel() }}</span>
                                <span class="text-sm text-ink/60">{{ $claimable_order->display_reference }} · {{ $claimable_order->created_at->format('j M Y') }}</span>
                            </span>
                            <x-public.status-badge :status="$claimable_order->status" />
                        </li>
                    @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('my-requests.claim') }}">
                        @csrf
                        @foreach ($claimable_orders as $claimable_order)
                            <input type="hidden" name="orders[]" value="{{ $claimable_order->id }}">
                        @endforeach
                        <button type="submit" class="inline-flex min-h-[48px] items-center justify-center rounded-xl border-2 border-ink bg-accent px-6 font-semibold text-ink shadow-pop-sm hover:bg-accent-600">
                            Yes, add {{ $claimable_orders->count() === 1 ? 'it' : 'them' }} to my account
                        </button>
                    </form>
                    <form method="POST" action="{{ route('my-requests.claim.dismiss') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-[48px] items-center justify-center rounded-xl border-2 border-ink bg-white px-6 font-semibold text-ink hover:bg-surface">
                            Not now
                        </button>
                    </form>
                </div>
                <p class="mt-2 text-sm text-ink/70">Nothing changes unless you accept. "Not now" stops us asking about these requests.</p>
            </section>
        @elseif (! $email_verified)
            <div class="mt-6 rounded-xl border-2 border-ink bg-highlight/40 p-4 text-ink">
                Placed a request without signing in?
                <a href="{{ route('verification.notice') }}" class="font-semibold text-accent-800 underline">Verify your email address</a>
                and we'll offer to add requests made with that email to your account.
            </div>
        @endif

        <nav class="mt-8 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Account sections">
            <ul class="flex min-w-max gap-1 border-b-2 border-ink/15">
                @foreach ($tabs as $tab_key => $tab_label)
                    <li>
                        <a href="{{ route('my-requests', ['tab' => $tab_key]) }}"
                            @if ($tab === $tab_key) aria-current="page" @endif
                            @class([
                                '-mb-0.5 inline-flex min-h-[44px] items-center gap-2 border-b-[3px] px-3 text-sm font-semibold',
                                'border-accent text-ink' => $tab === $tab_key,
                                'border-transparent text-ink/60 hover:text-ink' => $tab !== $tab_key,
                            ])>
                            {{ $tab_label }}
                            @if ($tab_key === 'messages')
                                @if ($tab_counts['messages'] > 0)
                                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white">{{ $tab_counts['messages'] }} new</span>
                                @endif
                            @elseif ($tab_counts[$tab_key] > 0)
                                <span class="rounded-full bg-ink/5 px-2 py-0.5 text-xs text-ink/80">{{ $tab_counts[$tab_key] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="mt-6">
            @include('account.partials.'.$tab)
        </div>
    </section>
</x-public-layout>
