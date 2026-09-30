@props([
    'title' => null,
    'metaTitle' => null,
    'description' => null,
    'canonicalUrl' => null,
    'ogImage' => null,
    'refreshUrl' => null,
])
{{--
    Public site layout. CSS only: no JavaScript is needed to read or use any page.

    Multi-word props must be camelCase here and kebab-case where passed
    (:refresh-url="..."). Blade hands camelCase props over as raw values; a
    snake_case prop only matches the attribute bag, where Blade has already
    escaped it, so {{ }} would escape it twice ("&amp;amp;").
--}}
@php($page_title = filled($metaTitle) ? $metaTitle : ($title ? $title.' | ' : '').config('madeeasy.contact.business_name'))
@php($page_description = $description ?? 'Reliable educational and documentation services: WAEC and NECO registration, admission guidance, result checking, certificate processing and JAMB services.')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- metaTitle (e.g. a post's SEO title) is used exactly as written; otherwise "Title | Business". --}}
        <title>{{ $page_title }}</title>
        <meta name="description" content="{{ $page_description }}">
        @if ($canonicalUrl)
            <link rel="canonical" href="{{ $canonicalUrl }}">
            <meta property="og:type" content="article">
            <meta property="og:url" content="{{ $canonicalUrl }}">
            <meta property="og:title" content="{{ $page_title }}">
            <meta property="og:description" content="{{ $page_description }}">
            @if ($ogImage)
                <meta property="og:image" content="{{ $ogImage }}">
            @endif
        @endif

        @if ($refreshUrl)
            {{-- Re-checks without JavaScript (e.g. waiting for a payment to be confirmed). --}}
            <meta http-equiv="refresh" content="5;url={{ $refreshUrl }}">
        @endif

        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col bg-slate-50 font-sans text-slate-800 antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:shadow">
            Skip to content
        </a>

        @include('partials.public.header')

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        @include('partials.public.footer')

        {{-- PLACEHOLDER (PDF section 14): site-wide floating WhatsApp button. Built in a later prompt. --}}
    </body>
</html>
