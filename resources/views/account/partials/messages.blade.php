{{-- Messages / Notifications: $messages (every order's history, newest first), $unread_ids (new since the last visit). --}}
@if ($messages->isEmpty())
    <x-public.empty-state title="No messages yet.">Updates on your requests will appear here.</x-public.empty-state>
@else
    <ol class="space-y-3">
        @foreach ($messages as $status_change)
            @php($is_unread = in_array($status_change->id, $unread_ids, true))
            <li @class([
                'rounded-2xl border p-4',
                'border-accent-200 bg-accent-50' => $status_change->by_customer,
                'border-ink bg-white ring-1 ring-ink' => ! $status_change->by_customer && $is_unread,
                'border-ink/20 bg-white' => ! $status_change->by_customer && ! $is_unread,
            ])>
                <p class="flex flex-wrap items-center gap-x-2 text-sm text-ink/60">
                    @if ($is_unread)
                        <span class="rounded-full bg-ink px-2 py-0.5 text-xs font-semibold text-white">New</span>
                    @else
                        <span class="sr-only">Read</span>
                    @endif
                    <span class="font-semibold text-ink/80">{{ $status_change->by_customer ? 'You' : ($status_change->to_status === \App\Enums\OrderStatus::Paid && $status_change->changed_by === null ? 'Payment' : 'Made Easy team') }}</span>
                    · {{ $status_change->created_at->format('j M Y, g:ia') }}
                    · <a href="{{ route('orders.track.show', $status_change->order) }}" class="underline">{{ $status_change->order->serviceLabel() }} ({{ $status_change->order->display_reference }})</a>
                </p>
                <p class="mt-2 font-semibold text-ink">
                    {{ $status_change->from_status === $status_change->to_status ? 'Update' : 'Status: '.$status_change->to_status->getLabel() }}
                </p>
                <p class="mt-1 whitespace-pre-line text-ink/85">{{ filled($status_change->note) ? $status_change->note : $status_change->to_status->customerDescription() }}</p>
            </li>
        @endforeach
    </ol>
    <div class="mt-4">{{ $messages->links() }}</div>
@endif
