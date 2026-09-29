@props([
    'title' => null,
    'description' => null,
])
{{-- Public site layout. CSS only: no JavaScript is needed to read or use any page. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title.' | ' : '' }}{{ config('madeeasy.contact.business_name') }}</title>
        <meta name="description" content="{{ $description ?? 'Reliable educational and documentation services: WAEC and NECO registration, admission guidance, result checking, certificate processing and JAMB services.' }}">

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
