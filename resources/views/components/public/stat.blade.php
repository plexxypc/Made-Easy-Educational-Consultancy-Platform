@props([
    'value',
    'label',
    'compact' => false,
])
{{--
    Stat badge: a bold number with its label. Use inside a <dl>. Only pass real figures
    (from the database or Site settings); leave a stat out rather than invent one.
    compact: smaller number, for the customer dashboard.
--}}
<div {{ $attributes->class(['flex flex-col-reverse justify-end rounded-2xl border-2 border-ink bg-white', 'px-4 py-3' => ! $compact, 'px-3 py-2' => $compact]) }}>
    <dt class="text-sm font-medium leading-snug text-ink/70">{{ $label }}</dt>
    <dd @class(['font-display font-bold leading-tight text-ink', 'text-3xl' => ! $compact, 'text-2xl' => $compact])>{{ $value }}</dd>
</div>
