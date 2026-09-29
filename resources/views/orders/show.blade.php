@php($input_classes = 'block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout :title="'Request #'.$order->id">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        @if ($can_download)
            <a href="{{ route('my-requests') }}" class="text-sm text-slate-500 hover:underline">&larr; My Requests</a>
        @endif

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Reference #{{ $order->id }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">{{ $order->service->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Submitted {{ $order->created_at->format('j M Y, g:ia') }}</p>
            </div>
            <x-public.status-badge :status="$order->status" class="text-base" />
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('status') }}</div>
        @endif

        <p class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 text-lg text-slate-800 shadow-sm">
            {{ $order->status->customerDescription() }}
        </p>

        @if ($order->status === \App\Enums\OrderStatus::NeedsAction)
            <section class="mt-8 rounded-2xl border-2 border-red-200 bg-red-50/60 p-5 sm:p-6" aria-labelledby="needs-action-heading">
                <h2 id="needs-action-heading" class="text-xl font-bold text-red-900">What we need from you</h2>
                <p class="mt-3 whitespace-pre-line text-base text-slate-900">{{ $needs_action_note ?? 'Please contact us for details.' }}</p>

                @if ($errors->any())
                    <div class="mt-5 rounded-xl border border-red-300 bg-white p-4 text-red-800" role="alert">
                        <ul class="list-inside list-disc text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('orders.respond', $order) }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                    @csrf

                    <div>
                        <label for="note" class="block text-sm font-semibold text-slate-800">Your message</label>
                        <textarea id="note" name="note" rows="4" maxlength="5000"
                            @error('note') aria-invalid="true" @enderror
                            class="{{ $input_classes }} mt-1">{{ old('note') }}</textarea>
                    </div>

                    <div>
                        <label for="documents" class="block text-sm font-semibold text-slate-800">Documents</label>
                        <input id="documents" name="documents[]" type="file" multiple
                            accept="{{ collect($upload_rules['allowed_extensions'])->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                            aria-describedby="documents-help"
                            class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-base text-slate-700 file:mr-4 file:min-h-[48px] file:border-0 file:bg-brand-50 file:px-4 file:font-semibold file:text-brand-800 hover:file:bg-brand-100">
                        <p id="documents-help" class="mt-1 text-sm text-slate-500">
                            Up to {{ $max_documents }} files. PDF, JPG or PNG, up to {{ \Illuminate\Support\Number::fileSize($upload_rules['max_kilobytes'] * 1024) }} each.
                        </p>
                    </div>

                    <p class="text-sm text-slate-600">Add a message, documents, or both.</p>

                    <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                        Send response
                    </button>
                </form>
            </section>
        @endif

        @if (count($answers) > 0)
            <section class="mt-8" aria-labelledby="answers-heading">
                <h2 id="answers-heading" class="text-xl font-bold text-slate-900">Your answers</h2>
                <dl class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @foreach ($answers as $label => $answer)
                        <div class="grid gap-1 px-5 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt class="text-sm font-semibold text-slate-600">{{ $label }}</dt>
                            <dd class="whitespace-pre-line text-slate-900 sm:col-span-2">{{ $answer }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="mt-8" aria-labelledby="documents-heading">
            <h2 id="documents-heading" class="text-xl font-bold text-slate-900">Your documents</h2>
            @if ($order->documents->isEmpty())
                <p class="mt-3 text-slate-600">No documents uploaded.</p>
            @else
                {{-- Guests see filenames only; downloads need a signed-in owner (OrderDocumentPolicy). --}}
                <ul class="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @foreach ($order->documents as $document)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                            @if ($can_download)
                                <a href="{{ route('order-documents.show', $document) }}" class="font-medium text-brand-700 underline" target="_blank" rel="noopener">{{ $document->original_filename }}</a>
                            @else
                                <span class="font-medium text-slate-900">{{ $document->original_filename }}</span>
                            @endif
                            <span class="text-sm text-slate-500">{{ $document->uploaded_at->format('j M Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($timeline->isNotEmpty())
            <section class="mt-8" aria-labelledby="updates-heading">
                <h2 id="updates-heading" class="text-xl font-bold text-slate-900">Messages</h2>
                <ol class="mt-3 space-y-3">
                    @foreach ($timeline as $status_change)
                        <li @class([
                            'rounded-2xl border p-4 shadow-sm',
                            'border-brand-200 bg-brand-50/50' => $status_change->by_customer,
                            'border-slate-200 bg-white' => ! $status_change->by_customer,
                        ])>
                            <p class="text-sm text-slate-500">
                                <span class="font-semibold text-slate-700">{{ $status_change->by_customer ? 'You' : 'Made Easy team' }}</span>
                                · {{ $status_change->created_at->format('j M Y, g:ia') }}
                                · {{ $status_change->to_status->getLabel() }}
                            </p>
                            <p class="mt-2 whitespace-pre-line text-slate-900">{{ $status_change->note }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <p class="mt-10 text-sm text-slate-600">
            Questions? <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a> and quote reference #{{ $order->id }}.
        </p>
    </section>
</x-public-layout>
