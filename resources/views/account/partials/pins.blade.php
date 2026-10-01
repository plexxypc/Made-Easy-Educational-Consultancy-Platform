{{-- Result Checker Tokens: $pins (delivered PINs on the customer's own orders). Hidden until "Show PIN"; LogPinAccess records staff/admin viewers only. --}}
@if ($pins->isEmpty())
    <x-public.empty-state title="No result checker PINs yet.">
        <x-slot:action>
            <x-public.button :href="route('result-checker')">Buy Result Checker</x-public.button>
        </x-slot:action>
    </x-public.empty-state>
@else
    <p class="text-sm text-ink/70">Keep your PINs private: anyone with a PIN can use it.</p>
    <ul class="mt-3 divide-y divide-ink/10 rounded-2xl border border-ink/20 bg-white">
        @foreach ($pins as $pin)
            <li class="grid gap-2 px-5 py-4 sm:grid-cols-2 sm:items-center">
                <span>
                    <span class="block font-semibold text-ink">{{ $pin->order->service->name }}</span>
                    <span class="text-sm text-ink/60">
                        <a href="{{ route('orders.track.show', $pin->order) }}" class="underline">{{ $pin->order->display_reference }}</a>
                        · {{ $pin->revealed_at?->format('j M Y') }}
                    </span>
                </span>
                <details class="group">
                    <summary class="inline-flex min-h-[44px] cursor-pointer list-none items-center rounded-xl border-2 border-ink bg-white px-4 text-sm font-semibold text-ink hover:bg-surface">
                        <span class="group-open:hidden">Show PIN</span>
                        <span class="hidden group-open:inline">Hide PIN</span>
                    </summary>
                    <p class="mt-2 font-mono text-xl font-bold tracking-wider text-ink">{{ $pin->pin }}</p>
                    @if ($pin->serial_number)
                        <p class="text-sm text-ink/70">Serial: <span class="font-mono text-ink">{{ $pin->serial_number }}</span></p>
                    @endif
                </details>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $pins->links() }}</div>
@endif
