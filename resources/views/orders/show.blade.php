@php($input_classes = 'block w-full rounded-xl border-slate-300 text-base shadow-sm focus:border-brand-500 focus:ring-brand-500')

<x-public-layout :title="'Request '.$order->display_reference">
    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        @if ($can_download)
            <a href="{{ route('my-requests') }}" class="text-sm text-slate-500 hover:underline">&larr; My Requests</a>
        @endif

        <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Reference {{ $order->display_reference }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-slate-900">{{ $order->serviceLabel() }}</h1>
                <p class="mt-1 text-sm text-slate-500">Submitted {{ $order->created_at->format('j M Y, g:ia') }}</p>
            </div>
            <x-public.status-badge :status="$order->status" class="text-base" />
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900" role="status">{{ session('status') }}</div>
        @endif

        <p class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 text-lg text-slate-800 shadow-sm">
            @if ($order->isResultChecker() && $order->status === \App\Enums\OrderStatus::Paid)
                Payment received. We're sending your {{ $order->quantity > 1 ? 'PINs' : 'PIN' }}.
            @else
                {{ $order->status->customerDescription() }}
            @endif
        </p>

        @if ($pins->isNotEmpty())
            {{-- The customer's own PINs: shown in full, not audited (LogPinAccess only records staff/admin viewers). --}}
            <section class="mt-6 rounded-2xl border-2 border-emerald-300 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="pins-heading">
                <h2 id="pins-heading" class="text-xl font-bold text-slate-900">Your {{ $pins->count() > 1 ? 'PINs' : 'PIN' }}</h2>
                <p class="mt-1 text-sm text-slate-600">Keep {{ $pins->count() > 1 ? 'them' : 'it' }} private: anyone with a PIN can use it. We've also emailed {{ $pins->count() > 1 ? 'them' : 'it' }} to you.</p>
                <ol class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach ($pins as $pin)
                        <li class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:items-center">
                            <span class="text-sm font-semibold text-slate-500">{{ $order->service->name }} {{ $pins->count() > 1 ? '#'.$loop->iteration : '' }}</span>
                            <span class="font-mono text-xl font-bold tracking-wider text-slate-900">{{ $pin->pin }}</span>
                            <span class="text-sm text-slate-600">
                                @if ($pin->serial_number)
                                    Serial: <span class="font-mono text-slate-900">{{ $pin->serial_number }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($undelivered_pins > 0)
            <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="pins-pending-heading">
                <h2 id="pins-pending-heading" class="text-lg font-bold text-amber-900">
                    {{ $undelivered_pins }} {{ $undelivered_pins === 1 ? 'PIN is' : 'PINs are' }} on the way
                </h2>
                <p class="mt-1 text-slate-800">
                    Your payment is confirmed. Our team is sending {{ $undelivered_pins === 1 ? 'your PIN' : 'the rest of your PINs' }} and will email you as soon as {{ $undelivered_pins === 1 ? "it's" : "they're" }} ready, usually within working hours. You don't need to do anything or pay again.
                </p>
            </section>
        @endif

        @error('payment')
            <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">{{ $message }}</div>
        @enderror

        @if ($receipt)
            <section class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-5" aria-labelledby="payment-heading">
                <h2 id="payment-heading" class="text-lg font-bold text-emerald-900">Paid: {{ $receipt->amount_label }}</h2>
                <p class="mt-1 text-slate-700">Receipt {{ $receipt->number }} · {{ $receipt->paid_at->format('j M Y, g:ia') }}</p>
                <div class="mt-4 grid gap-3 sm:flex">
                    <x-public.button :href="route('orders.receipt', $order)">View receipt</x-public.button>
                    <x-public.button :href="route('orders.receipt', ['order' => $order, 'download' => 1])" variant="secondary">Download receipt</x-public.button>
                </div>
            </section>
        @elseif ($order->canBePaid())
            <section class="mt-6 rounded-2xl border-2 border-brand-200 bg-brand-50/60 p-5 sm:p-6" aria-labelledby="payment-heading">
                <h2 id="payment-heading" class="text-xl font-bold text-slate-900">Amount to pay: {{ \App\Support\Money::format($order->total_amount) }}</h2>
                <p class="mt-2 text-slate-700">Pay securely by card, bank transfer or USSD through Paystack. You'll come back here afterwards.</p>
                <form method="POST" action="{{ route('orders.pay', $order) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex min-h-[52px] w-full items-center justify-center rounded-xl bg-brand-700 px-8 text-base font-semibold text-white shadow-sm hover:bg-brand-800 focus:outline-none focus-visible:ring-4 focus-visible:ring-brand-300 sm:w-auto">
                        Pay {{ \App\Support\Money::format($order->total_amount) }} now
                    </button>
                </form>
            </section>
        @elseif ($order->status === \App\Enums\OrderStatus::PendingPayment && $order->awaitsQuote())
            <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5" aria-labelledby="payment-heading">
                <h2 id="payment-heading" class="text-lg font-bold text-amber-900">Price not set yet</h2>
                <p class="mt-1 text-slate-800">{{ $order->paymentBlocker() }}</p>
            </section>
        @endif

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
                                <span class="font-semibold text-slate-700">{{ $status_change->by_customer ? 'You' : ($status_change->to_status === \App\Enums\OrderStatus::Paid && $status_change->changed_by === null ? 'Payment' : 'Made Easy team') }}</span>
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
            Questions? <a href="{{ route('contact') }}" class="font-semibold text-brand-700 underline">Contact us</a> and quote reference {{ $order->display_reference }}.
        </p>
    </section>
</x-public-layout>
