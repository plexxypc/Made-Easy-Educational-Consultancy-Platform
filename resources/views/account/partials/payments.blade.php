{{-- Payment History: $payments (successful payments with receipts). Receipts use the existing receipt route. --}}
@if ($payments->isEmpty())
    <x-public.empty-state title="No payments yet." />
@else
    <ul class="divide-y divide-ink/10 rounded-2xl border border-ink/20 bg-white">
        @foreach ($payments as $payment)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <span>
                    <span class="block font-semibold text-ink">{{ $payment->order->serviceLabel() }}</span>
                    <span class="text-sm text-ink/60">
                        {{ $payment->order->display_reference }}
                        · {{ \App\Models\Receipt::lagos_time_label($payment->paid_at ?? $payment->updated_at, 'j M Y, g:ia') }}
                        @if ($payment->receipt)
                            · Receipt {{ $payment->receipt->number }}
                        @endif
                    </span>
                </span>
                <span class="flex flex-wrap items-center gap-3">
                    <span class="text-lg font-bold text-ink">{{ \App\Support\Money::format($payment->paid_amount ?? $payment->amount) }}</span>
                    @if ($payment->receipt)
                        <a href="{{ route('orders.receipt', $payment->order) }}" class="text-sm font-semibold text-accent-700 underline">View receipt</a>
                        <a href="{{ route('orders.receipt', ['order' => $payment->order, 'download' => 1]) }}" class="text-sm font-semibold text-accent-700 underline">Download</a>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $payments->links() }}</div>
@endif
