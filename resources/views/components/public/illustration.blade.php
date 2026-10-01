@props([
    'name',
    'eager' => false,
])
{{--
    One unDraw illustration (undraw.co, free licence) from resources/images/illustrations/, recoloured to
    the palette. Decorative only (alt=""): the text next to it carries the meaning. Size it with classes,
    e.g. class="h-32 w-auto"; width/height here only reserve the space so nothing jumps while it loads.
--}}
@php
    $intrinsic_sizes = [
        'hero-requests' => [800, 661],
        'empty-requests' => [800, 773],
        'empty-consultations' => [960, 743],
        'empty-documents' => [800, 739],
    ];
    [$width, $height] = $intrinsic_sizes[$name];
@endphp
<img
    src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/illustrations/'.$name.'.svg') }}"
    alt=""
    width="{{ $width }}"
    height="{{ $height }}"
    decoding="async"
    @unless ($eager) loading="lazy" @endunless
    {{ $attributes }}
>
