<x-public-layout title="My Requests">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">My Requests</h1>
        <p class="mt-2 text-slate-600">Requests you placed while signed in to this account.</p>

        @if ($orders->isEmpty())
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
                <p class="text-lg font-semibold text-slate-900">You haven't made any requests yet.</p>
                <p class="mt-2 text-slate-600">
                    Requests sent without signing in aren't listed here; use
                    <a href="{{ route('orders.track') }}" class="font-semibold text-brand-700 underline">Track your request</a> for those.
                </p>
                <x-public.button :href="route('services.index')" class="mt-5">Browse services</x-public.button>
            </div>
        @else
            <ul class="mt-6 space-y-3">
                @foreach ($orders as $order)
                    <li class="rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
                        <a href="{{ route('orders.track.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-5">
                            <span>
                                <span class="block text-sm text-slate-500">{{ $order->display_reference }} · {{ $order->created_at->format('j M Y') }}</span>
                                <span class="mt-1 block text-lg font-semibold text-slate-900">{{ $order->serviceLabel() }}</span>
                            </span>
                            <x-public.status-badge :status="$order->status" />
                        </a>
                        @if ($order->pins->isNotEmpty())
                            <div class="border-t border-slate-100 px-5 py-3">
                                <p class="text-sm font-semibold text-slate-600">Your {{ $order->pins->count() > 1 ? 'PINs' : 'PIN' }}</p>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($order->pins as $pin)
                                        <li class="font-mono text-lg font-bold tracking-wider text-slate-900">
                                            {{ $pin->pin }}
                                            @if ($pin->serial_number)
                                                <span class="ml-2 font-sans text-sm font-normal text-slate-600">Serial {{ $pin->serial_number }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($order->canBePaid())
                            <form method="POST" action="{{ route('orders.pay', $order) }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
                                @csrf
                                <span class="text-sm text-slate-600">Amount due: <strong class="text-slate-900">{{ \App\Support\Money::format($order->total_amount) }}</strong></span>
                                <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl bg-brand-700 px-5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300">
                                    Pay now
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </section>
</x-public-layout>
