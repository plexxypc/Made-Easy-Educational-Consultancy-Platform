{{-- One request on the dashboard. $order: Order with service, consultationBooking.slot, receipt, testimonial_exists. --}}
<li class="rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:border-brand-300 hover:shadow">
    <a href="{{ route('orders.track.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 p-5">
        <span>
            <span class="block text-sm text-slate-500">{{ $order->display_reference }} · {{ $order->created_at->format('j M Y') }}</span>
            <span class="mt-1 block text-lg font-semibold text-slate-900">{{ $order->serviceLabel() }}</span>
        </span>
        <x-public.status-badge :status="$order->status" />
    </a>
    @if ($order->consultationBooking)
        <div class="border-t border-slate-100 px-5 py-3 text-sm text-slate-700">
            <span class="font-semibold text-slate-900">{{ $order->consultationBooking->slot->label() }}</span>
            · {{ $order->consultationBooking->slot->consultation_type->getLabel() }}
            · {{ $order->consultationBooking->statusLabel() }}
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
    @elseif ($order->receipt)
        <div class="border-t border-slate-100 px-5 py-3 text-sm text-slate-600">
            Paid {{ $order->receipt->amount_label }} ·
            <a href="{{ route('orders.receipt', $order) }}" class="font-semibold text-brand-700 underline">Receipt {{ $order->receipt->number }}</a>
        </div>
    @endif
    @if ($order->status === \App\Enums\OrderStatus::Completed && ! $order->testimonial_exists)
        <div class="border-t border-slate-100 px-5 py-3 text-sm">
            <a href="{{ route('orders.testimonial', $order) }}" class="font-semibold text-brand-700 underline">Share your experience</a>
        </div>
    @endif
</li>
