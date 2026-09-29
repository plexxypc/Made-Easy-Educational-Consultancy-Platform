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
                    <li>
                        <a href="{{ route('orders.track.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-300 hover:shadow">
                            <span>
                                <span class="block text-sm text-slate-500">#{{ $order->id }} · {{ $order->created_at->format('j M Y') }}</span>
                                <span class="mt-1 block text-lg font-semibold text-slate-900">{{ $order->service->name }}</span>
                            </span>
                            <x-public.status-badge :status="$order->status" />
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </section>
</x-public-layout>
