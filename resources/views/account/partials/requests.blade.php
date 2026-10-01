{{-- Services Purchased / Applications: $pending_orders, $completed_orders (paginators). --}}
@if ($pending_orders->isEmpty() && $completed_orders->isEmpty() && $pending_orders->currentPage() === 1 && $completed_orders->currentPage() === 1)
    <x-public.empty-state illustration="empty-requests">
        <x-slot:title>You haven't made any requests yet.</x-slot:title>
        Requests sent without signing in aren't listed here; use
        <a href="{{ route('orders.track') }}" class="font-semibold text-accent-700 underline">Track your request</a> for those.
        <x-slot:action>
            <x-public.button :href="route('services.index')">Browse services</x-public.button>
        </x-slot:action>
    </x-public.empty-state>
@else
    <section aria-labelledby="pending-heading">
        <h2 id="pending-heading" class="text-xl font-bold text-ink">Pending</h2>
        @if ($pending_orders->isEmpty())
            <p class="mt-2 text-ink/70">Nothing in progress.</p>
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
        <h2 id="completed-heading" class="text-xl font-bold text-ink">Completed</h2>
        <p class="mt-1 text-sm text-ink/60">Finished and cancelled requests.</p>
        @if ($completed_orders->isEmpty())
            <p class="mt-2 text-ink/70">No completed requests yet.</p>
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

{{-- After "Not now" on the claim prompt: a quiet way back, never the prompt itself. --}}
@if ($dismissed_claim_count > 0)
    <form method="POST" action="{{ route('my-requests.claim.restore') }}" class="mt-8 border-t border-ink/15 pt-4 text-sm text-ink/70">
        @csrf
        {{ trans_choice('You chose not to add :count request placed as a guest with your email.|You chose not to add :count requests placed as a guest with your email.', $dismissed_claim_count) }}
        <button type="submit" class="font-semibold text-accent-700 underline">Review {{ $dismissed_claim_count === 1 ? 'it' : 'them' }}</button>
    </form>
@endif
