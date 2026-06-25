<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Personal Dashboard') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen antialiased text-ink">
    <div class="aurora"></div>
    <div class="grid-veil"></div>

    <main class="relative z-10 flex min-h-screen items-center justify-center px-6 py-12">
        <div class="w-full max-w-md">
            <div class="mb-10 text-center reveal">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-2.5">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-cyan-soft text-cyan font-display text-lg font-bold text-glow-cyan">P</span>
                    <span class="font-display text-xl font-bold tracking-tight">Personal<span class="text-cyan">Dashboard</span></span>
                </a>
            </div>

            {{ $slot }}

            <p class="mt-8 text-center text-xs text-faint">
                {{ now()->year }} — Your modules, one command deck.
            </p>
        </div>
    </main>

    @livewireScripts
</body>
</html>
