{{-- Services Purchased / Applications: $pending_orders, $completed_orders (paginators). --}}
@if ($pending_orders->isEmpty() && $completed_orders->isEmpty() && $pending_orders->currentPage() === 1 && $completed_orders->currentPage() === 1)
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <p class="text-lg font-semibold text-slate-900">You haven't made any requests yet.</p>
        <p class="mt-2 text-slate-600">
            Requests sent without signing in aren't listed here; use
            <a href="{{ route('orders.track') }}" class="font-semibold text-brand-700 underline">Track your request</a> for those.
        </p>
        <x-public.button :href="route('services.index')" class="mt-5">Browse services</x-public.button>
    </div>
@else
    <section aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="text-xl font-bold text-slate-900">Pending</h2>
        @if ($pending_orders->isEmpty())
            <p class="mt-2 text-slate-600">Nothing in progress.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($pending_orders as $order)
                    @include('account.partials.order-card', ['order' => $order])
                @endforeach
            </ul>
            <div class="mt-4">{{ $pending_orders->links() }}</div>
        @endif
    </section>

    <section class="mt-10" aria-labelledby="completed-heading">
        <h2 id="completed-heading" class="text-xl font-bold text-slate-900">Completed</h2>
        <p class="mt-1 text-sm text-slate-500">Finished and cancelled requests.</p>
        @if ($completed_orders->isEmpty())
            <p class="mt-2 text-slate-600">No completed requests yet.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($completed_orders as $order)
                    @include('account.partials.order-card', ['order' => $order])
                @endforeach
            </ul>
            <div class="mt-4">{{ $completed_orders->links() }}</div>
        @endif
    </section>
@endif
