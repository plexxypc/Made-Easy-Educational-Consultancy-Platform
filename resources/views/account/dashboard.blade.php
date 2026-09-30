{{-- Customer account dashboard ("My Requests", PDF section 8). Data: CustomerDashboardController::show(). --}}
<x-public-layout title="My Requests">
    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">My Requests</h1>
        <p class="mt-2 text-slate-600">Everything for your account: requests, payments, documents, consultations, PINs and messages.</p>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('status') }}</div>
        @endif

        @error('claim')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">{{ $message }}</div>
        @enderror

        @if ($claimable_orders->isNotEmpty())
            {{-- Only for the account's own verified email (FindClaimableGuestOrders); nothing is linked until they accept. --}}
            <section class="mt-6 rounded-2xl border-2 border-brand-300 bg-brand-50/60 p-5 shadow-sm sm:p-6" aria-labelledby="claim-heading">
                <h2 id="claim-heading" class="text-lg font-bold text-slate-900">
                    We found {{ $claimable_orders->count() }} {{ $claimable_orders->count() === 1 ? 'request' : 'requests' }} placed as a guest with your email — add {{ $claimable_orders->count() === 1 ? 'it' : 'them' }} to your account?
                </h2>
                <p class="mt-1 text-slate-700">These were sent without signing in, using {{ auth()->user()->email }}. Once added, they appear here and you won't need to look them up again.</p>
                <ul class="mt-3 divide-y divide-brand-100 rounded-xl border border-brand-200 bg-white">
                    @foreach ($claimable_orders as $claimable_order)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                            <span>
                                <span class="block font-semibold text-slate-900">{{ $claimable_order->serviceLabel() }}</span>
                                <span class="text-sm text-slate-500">{{ $claimable_order->display_reference }} · {{ $claimable_order->created_at->format('j M Y') }}</span>
                            </span>
                            <x-public.status-badge :status="$claimable_order->status" />
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('my-requests.claim') }}" class="mt-4">
                    @csrf
                    @foreach ($claimable_orders as $claimable_order)
                        <input type="hidden" name="orders[]" value="{{ $claimable_order->id }}">
                    @endforeach
                    <button type="submit" class="inline-flex min-h-[48px] items-center justify-center rounded-xl bg-brand-700 px-6 font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">
                        Yes, add {{ $claimable_orders->count() === 1 ? 'it' : 'them' }} to my account
                    </button>
                    <span class="ml-2 text-sm text-slate-600">Not yours? Just ignore this; nothing changes unless you accept.</span>
                </form>
            </section>
        @elseif (! $email_verified)
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-slate-800">
                Placed a request without signing in?
                <a href="{{ route('verification.notice') }}" class="font-semibold text-brand-700 underline">Verify your email address</a>
                and we'll offer to add requests made with that email to your account.
            </div>
        @endif

        <nav class="mt-8 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Account sections">
            <ul class="flex min-w-max gap-2 border-b border-slate-200">
                @foreach ($tabs as $tab_key => $tab_label)
                    <li>
                        <a href="{{ route('my-requests', ['tab' => $tab_key]) }}"
                            @if ($tab === $tab_key) aria-current="page" @endif
                            @class([
                                'inline-flex min-h-[44px] items-center gap-2 border-b-2 px-3 text-sm font-semibold',
                                'border-brand-700 text-brand-800' => $tab === $tab_key,
                                'border-transparent text-slate-600 hover:text-slate-900' => $tab !== $tab_key,
                            ])>
                            {{ $tab_label }}
                            @if ($tab_key === 'messages')
                                @if ($tab_counts['messages'] > 0)
                                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs text-white">{{ $tab_counts['messages'] }} new</span>
                                @endif
                            @elseif ($tab_counts[$tab_key] > 0)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ $tab_counts[$tab_key] }}</span>
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
