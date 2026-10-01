@props([
    'title',
    'illustration' => null,
])
{{--
    "Nothing here yet" panel: optional small illustration, a title, a short explanation (slot)
    and one clear next step ($action slot). Illustrations only for genuinely empty lists.
    title: an attribute (escaped) or a <x-slot:title> (written exactly as in the template).
--}}
<div {{ $attributes->class(['rounded-2xl border border-ink/15 bg-white px-6 py-8 text-center']) }}>
    @if ($illustration)
        <x-public.illustration :name="$illustration" class="mx-auto h-28 w-auto sm:h-32" />
    @endif
    <p @class(['font-display text-lg font-bold text-ink', 'mt-5' => $illustration])>{{ $title }}</p>
    @if ($slot->isNotEmpty())
        <div class="mx-auto mt-2 max-w-md text-ink/70">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
