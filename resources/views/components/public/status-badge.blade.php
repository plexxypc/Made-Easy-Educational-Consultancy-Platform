@props([
    'status',
])
{{-- Order status pill for the public site. $status is an App\Enums\OrderStatus. --}}
@php
    $status_classes = match ($status) {
        \App\Enums\OrderStatus::PendingPayment => 'bg-slate-100 text-slate-800 ring-slate-300',
        \App\Enums\OrderStatus::Paid => 'bg-sky-50 text-sky-800 ring-sky-200',
        \App\Enums\OrderStatus::InProgress => 'bg-amber-50 text-amber-900 ring-amber-200',
        \App\Enums\OrderStatus::Completed => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        \App\Enums\OrderStatus::NeedsAction => 'bg-red-50 text-red-800 ring-red-200',
        \App\Enums\OrderStatus::Cancelled => 'bg-slate-100 text-slate-600 ring-slate-300',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset', $status_classes]) }}>
    {{ $status->getLabel() }}
</span>
