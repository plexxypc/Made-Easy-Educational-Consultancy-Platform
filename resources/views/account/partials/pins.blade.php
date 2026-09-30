{{-- Result Checker Tokens: $pins (delivered PINs on the customer's own orders). Hidden until "Show PIN"; LogPinAccess records staff/admin viewers only. --}}
@if ($pins->isEmpty())
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <p class="text-slate-600">No result checker PINs yet.</p>
        <x-public.button :href="route('result-checker')" class="mt-4">Buy Result Checker</x-public.button>
    </div>
@else
    <p class="text-sm text-slate-600">Keep your PINs private: anyone with a PIN can use it.</p>
    <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
        @foreach ($pins as $pin)
            <li class="grid gap-2 px-5 py-4 sm:grid-cols-2 sm:items-center">
                <span>
                    <span class="block font-semibold text-slate-900">{{ $pin->order->service->name }}</span>
                    <span class="text-sm text-slate-500">
                        <a href="{{ route('orders.track.show', $pin->order) }}" class="underline">{{ $pin->order->display_reference }}</a>
                        · {{ $pin->revealed_at?->format('j M Y') }}
                    </span>
                </span>
                <details class="group">
                    <summary class="inline-flex min-h-[44px] cursor-pointer list-none items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        <span class="group-open:hidden">Show PIN</span>
                        <span class="hidden group-open:inline">Hide PIN</span>
                    </summary>
                    <p class="mt-2 font-mono text-xl font-bold tracking-wider text-slate-900">{{ $pin->pin }}</p>
                    @if ($pin->serial_number)
                        <p class="text-sm text-slate-600">Serial: <span class="font-mono text-slate-900">{{ $pin->serial_number }}</span></p>
                    @endif
                </details>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $pins->links() }}</div>
@endif
