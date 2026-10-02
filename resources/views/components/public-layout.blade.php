@props([
    'title' => null,
    'metaTitle' => null,
    'description' => null,
    'canonicalUrl' => null,
    'ogImage' => null,
    'refreshUrl' => null,
    'noindex' => false,
    'ogType' => 'website',
    'whatsappMessage' => null,
])
{{--
    Public site layout. CSS only: no JavaScript is needed to read or use any page.

    Multi-word props must be camelCase here and kebab-case where passed
    (:refresh-url="..."). Blade hands camelCase props over as raw values; a
    snake_case prop only matches the attribute bag, where Blade has already
    escaped it, so {{ }} would escape it twice ("&amp;amp;").

    SEO: every indexable page gets a canonical URL and Open Graph tags. The
    canonical defaults to the current URL without its query string, so
    paginated or filtered copies of a page point at the main one. Pages
    marked noindex (previews, order pages) get neither.
--}}
@php($page_title = filled($metaTitle) ? $metaTitle : ($title ? $title.' | ' : '').config('madeeasy.contact.business_name'))
@php($page_description = filled($description) ? $description : 'Reliable educational and documentation services: WAEC and NECO registration, admission guidance, result checking, certificate processing and JAMB services.')
@php($page_url = $canonicalUrl ?? url()->current())
{{-- Pages without their own share image (e.g. a post's featured image) get the logo banner. --}}
@php($share_image = filled($ogImage) ? $ogImage : asset('images/og-default.png'))
@php($whatsapp_url = \App\Support\WhatsApp::link($whatsappMessage))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#001848">

        {{-- Favicon set generated from the logo emblem (public/images/logo-mark.png). --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        {{-- metaTitle (e.g. a post's SEO title) is used exactly as written; otherwise "Title | Business". --}}
        <title>{{ $page_title }}</title>
        <meta name="description" content="{{ $page_description }}">
        @if ($noindex)
            <meta name="robots" content="noindex, nofollow">
        @else
            <link rel="canonical" href="{{ $page_url }}">
            <meta property="og:type" content="{{ $ogType }}">
            <meta property="og:site_name" content="{{ config('madeeasy.contact.business_name') }}">
            <meta property="og:url" content="{{ $page_url }}">
            <meta property="og:title" content="{{ $page_title }}">
            <meta property="og:description" content="{{ $page_description }}">
            <meta property="og:locale" content="en_NG">
            <meta property="og:image" content="{{ $share_image }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $share_image }}">
        @endif

        {{-- Structured data (JSON-LD) from pages, e.g. the FAQ. --}}
        {{ $head ?? '' }}

        @if ($refreshUrl)
            {{-- Re-checks without JavaScript (e.g. waiting for a payment to be confirmed). --}}
            <meta http-equiv="refresh" content="5;url={{ $refreshUrl }}">
        @endif

        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col bg-surface font-sans text-ink antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:border-2 focus:border-ink focus:bg-white focus:px-4 focus:py-3">
            Skip to content
        </a>

        @include('partials.public.header')

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        @include('partials.public.footer')

        {{-- PDF section 14: floating click-to-chat button. A plain wa.me link (App\Support\WhatsApp); nothing loads from WhatsApp until tapped. --}}
        @if ($whatsapp_url)
            <a
                href="{{ $whatsapp_url }}"
                target="_blank"
                rel="noopener"
                class="fixed bottom-4 right-4 z-50 inline-flex min-h-[56px] items-center gap-2 rounded-full border-2 border-ink bg-[#25D366] px-4 text-base font-semibold text-ink shadow-pop-sm hover:bg-[#1ebe5b] sm:bottom-6 sm:right-6"
                data-testid="whatsapp-button"
            >
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 21.5h-.01a9.4 9.4 0 0 1-4.8-1.31l-.34-.2-3.57.93.95-3.48-.22-.36a9.43 9.43 0 0 1-1.45-5.03c0-5.2 4.24-9.44 9.45-9.44 2.52 0 4.89.99 6.68 2.77a9.38 9.38 0 0 1 2.76 6.68c0 5.21-4.24 9.44-9.45 9.44zm8.04-17.48A11.3 11.3 0 0 0 12.04.7C5.77.7.67 5.8.67 12.07c0 2 .52 3.96 1.52 5.68L.57 23.7l6.08-1.6a11.33 11.33 0 0 0 5.39 1.37h.01c6.27 0 11.37-5.1 11.37-11.37 0-3.04-1.18-5.9-3.34-8.05z"/></svg>
                <span class="sr-only sm:not-sr-only">Chat on WhatsApp</span>
                <span class="sr-only">(opens WhatsApp)</span>
            </a>
        @endif
    </body>
</html>
