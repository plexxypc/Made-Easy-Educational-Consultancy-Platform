{{-- Uploaded Documents: $documents. Opened through /documents/{id} (OrderDocumentPolicy; only staff/admin views are audited). --}}
@if ($documents->isEmpty())
    <x-public.empty-state title="No documents uploaded." illustration="empty-documents">
        Files you attach to a request, or send when we ask for more information, appear here so you can open or download them again.
        <x-slot:action>
            <x-public.button :href="route('services.index')" variant="secondary">Browse services</x-public.button>
        </x-slot:action>
    </x-public.empty-state>
@else
    <ul class="divide-y divide-ink/10 rounded-2xl border border-ink/20 bg-white">
        @foreach ($documents as $document)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <span class="min-w-0">
                    <a href="{{ route('order-documents.show', $document) }}" class="break-all font-semibold text-accent-700 underline" target="_blank" rel="noopener">{{ $document->original_filename }}</a>
                    <span class="block text-sm text-ink/60">
                        {{ $document->order->serviceLabel() }} · {{ $document->order->display_reference }}
                        · {{ $document->uploaded_at->format('j M Y') }}
                        · {{ \Illuminate\Support\Number::fileSize($document->size) }}
                    </span>
                </span>
                <a href="{{ route('order-documents.show', ['order_document' => $document, 'download' => 1]) }}" class="text-sm font-semibold text-accent-700 underline">Download</a>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $documents->links() }}</div>
@endif
