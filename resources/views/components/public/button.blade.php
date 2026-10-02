@props([
    'href',
    'variant' => 'primary',
])
{{--
    Large, full-width-on-mobile link button (48px min tap target).
    primary: the blue call to action (one per section where possible); dark: a strong second choice;
    secondary: everything else. White text on the brand blue keeps WCAG AA contrast (7.6:1).
--}}
<a
    href="{{ $href }}"
    {{ $attributes->class([
        'inline-flex min-h-[52px] w-full items-center justify-center rounded-xl border-2 border-ink px-6 text-base font-semibold sm:w-auto',
        'bg-accent text-white shadow-pop hover:bg-accent-600' => $variant === 'primary',
        'bg-ink text-white hover:bg-ink/85' => $variant === 'dark',
        'bg-white text-ink hover:bg-surface' => $variant === 'secondary',
    ]) }}
>{{ $slot }}</a>
