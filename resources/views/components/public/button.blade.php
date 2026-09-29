@props([
    'href',
    'variant' => 'primary',
])
{{-- Large, full-width-on-mobile link button (48px min tap target). --}}
<a
    href="{{ $href }}"
    {{ $attributes->class([
        'inline-flex min-h-[52px] w-full items-center justify-center rounded-xl px-6 text-base font-semibold shadow-sm transition focus:outline-none focus-visible:ring-4 sm:w-auto',
        'bg-brand-700 text-white hover:bg-brand-800 focus-visible:ring-brand-300' => $variant === 'primary',
        'bg-accent-400 text-slate-900 hover:bg-accent-300 focus-visible:ring-accent-200' => $variant === 'accent',
        'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 focus-visible:ring-slate-200' => $variant === 'secondary',
    ]) }}
>{{ $slot }}</a>
