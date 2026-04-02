<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>{{ filled($title ?? null) ? $title : config('app.name', 'SKYmanager').' — Professional WiFi Hotspot Management' }}</title>
    <meta name="description" content="SKYmanager is the all-in-one WiFi billing and hotspot management platform built for ISPs and hotspot entrepreneurs in Tanzania. MikroTik integration, ClickPesa payments, customer self-service portal." />

    {{-- Open Graph --}}
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ config('app.name', 'SKYmanager') }} — Professional WiFi Hotspot Management" />
    <meta property="og:description" content="Complete MikroTik hotspot billing, self-service portals, and mobile payments — all in one platform." />
    <meta property="og:url" content="{{ url('/') }}" />

    <link rel="icon" href="/favicon.ico" sizes="any" />
    <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />

    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen bg-white dark:bg-zinc-950 font-sans antialiased">

    {{ $slot }}

    @fluxScripts
</body>
</html>
