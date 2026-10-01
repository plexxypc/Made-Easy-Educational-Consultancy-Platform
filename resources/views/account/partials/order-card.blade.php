{{-- One request on the dashboard. $order: Order with service, consultationBooking.slot, receipt, testimonial_exists. --}}
<li class="rounded-2xl border border-ink/20 bg-white hover:border-ink">
    <a href="{{ route('orders.track.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl p-5">
        <span>
            <span class="block text-sm text-ink/60">{{ $order->display_reference }} · {{ $order->created_at->format('j M Y') }}</span>
            <span class="mt-1 block text-lg font-semibold text-ink">{{ $order->serviceLabel() }}</span>
        </span>
        <x-public.status-badge :status="$order->status" />
    </a>
    @if ($order->consultationBooking)
        <div class="border-t border-ink/10 px-5 py-3 text-sm text-ink/80">
            <span class="font-semibold text-ink">{{ $order->consultationBooking->slot->label() }}</span>
            · {{ $order->consultationBooking->slot->consultation_type->getLabel() }}
            · {{ $order->consultationBooking->statusLabel() }}
        </div>
    @endif
    @if ($order->canBePaid())
        <form method="POST" action="{{ route('orders.pay', $order) }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-ink/10 px-5 py-3">
            @csrf
            <span class="text-sm text-ink/70">Amount due: <strong class="text-ink">{{ \App\Support\Money::format($order->total_amount) }}</strong></span>
            <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-xl border-2 border-ink bg-accent px-5 text-sm font-semibold text-ink shadow-pop-sm hover:bg-accent-600">
                Pay now
            </button>
        </form>
    @elseif ($order->receipt)
        <div class="border-t border-ink/10 px-5 py-3 text-sm text-ink/70">
            Paid {{ $order->receipt->amount_label }} ·
            <a href="{{ route('orders.receipt', $order) }}" class="font-semibold text-accent-700 underline">Receipt {{ $order->receipt->number }}</a>
        </div>
    @endif
    @if ($order->status === \App\Enums\OrderStatus::Completed && ! $order->testimonial_exists)
        <div class="border-t border-ink/10 px-5 py-3 text-sm">
            <a href="{{ route('orders.testimonial', $order) }}" class="font-semibold text-accent-700 underline">Share your experience</a>
        </div>
    @endif
</li>
