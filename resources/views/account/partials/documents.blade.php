{{-- Uploaded Documents: $documents. Opened through /documents/{id} (OrderDocumentPolicy; only staff/admin views are audited). --}}
@if ($documents->isEmpty())
    <p class="rounded-2xl border border-slate-200 bg-white p-6 text-slate-600 shadow-sm">No documents uploaded.</p>
@else
    <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
        @foreach ($documents as $document)
            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                <span class="min-w-0">
                    <a href="{{ route('order-documents.show', $document) }}" class="break-all font-semibold text-brand-700 underline" target="_blank" rel="noopener">{{ $document->original_filename }}</a>
                    <span class="block text-sm text-slate-500">
                        {{ $document->order->serviceLabel() }} · {{ $document->order->display_reference }}
                        · {{ $document->uploaded_at->format('j M Y') }}
                        · {{ \Illuminate\Support\Number::fileSize($document->size) }}
                    </span>
                </span>
                <a href="{{ route('order-documents.show', ['order_document' => $document, 'download' => 1]) }}" class="text-sm font-semibold text-brand-700 underline">Download</a>
            </li>
        @endforeach
    </ul>
    <div class="mt-4">{{ $documents->links() }}</div>
@endif
