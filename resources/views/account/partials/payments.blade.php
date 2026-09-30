{{-- Payment History: $payments (successful payments with receipts). Receipts use the existing receipt route. --}}
@if ($payments->isEmpty())
    <p class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-600 shadow-sm">No payments yet.</p>
@else
    <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
        @foreach ($payments as $payment)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <span>
                    <span class="block font-semibold text-slate-900">{{ $payment->order->serviceLabel() }}</span>
                    <span class="text-sm text-slate-500">
                        {{ $payment->order->display_reference }}
                        · {{ ($payment->paid_at ?? $payment->updated_at)->format('j M Y, g:ia') }}
                        @if ($payment->receipt)
                            · Receipt {{ $payment->receipt->number }}
                        @endif
                    </span>
                </span>
                <span class="flex flex-wrap items-center gap-3">
                    <span class="font-semibold text-slate-900">{{ \App\Support\Money::format($payment->paid_amount ?? $payment->amount) }}</span>
                    @if ($payment->receipt)
                        <a href="{{ route('orders.receipt', $payment->order) }}" class="text-sm font-semibold text-brand-700 underline">View receipt</a>
                        <a href="{{ route('orders.receipt', ['order' => $payment->order, 'download' => 1]) }}" class="text-sm font-semibold text-brand-700 underline">Download</a>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $payments->links() }}</div>
@endif
