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
<body class="min-h-screen antialiased text-ink" x-data="{ sidebarOpen: false }">
    <div class="aurora"></div>
    <div class="grid-veil"></div>

    <div class="relative z-10 flex min-h-screen">
        <x-ui.sidebar />

        <div
            x-show="sidebarOpen"
            x-transition.opacity
            @click="sidebarOpen = false"
            class="fixed inset-0 z-30 bg-void/70 backdrop-blur-sm lg:hidden"
            style="display: none;"
        ></div>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
            <header class="sticky top-0 z-20 px-4 pt-4 lg:px-8 lg:pt-6">
                <div class="glass flex items-center justify-between gap-4 px-4 py-3 lg:px-6">
                    <div class="flex items-center gap-3">
                        <button
                            @click="sidebarOpen = true"
                            class="grid h-9 w-9 place-items-center rounded-lg border border-hairline text-muted transition hover:text-cyan lg:hidden"
                            aria-label="Open navigation"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <div>
                            <p class="text-[0.7rem] uppercase tracking-[0.28em] text-faint">Command deck</p>
                            <h1 class="font-display text-lg font-semibold leading-tight">{{ $header ?? 'Dashboard' }}</h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-3" x-data="{ menu: false }">
                        <span class="hidden items-center gap-2 rounded-full border border-hairline px-3 py-1.5 text-xs text-muted sm:inline-flex">
                            <span class="h-2 w-2 rounded-full bg-lime" style="animation: pulse-ring 2.4s infinite;"></span>
                            Online
                        </span>

                        <div class="relative">
                            <button
                                @click="menu = !menu"
                                class="flex items-center gap-3 rounded-full border border-hairline py-1 pl-1 pr-3 transition hover:border-cyan/40"
                            >
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-violet-soft font-display text-sm font-semibold text-violet">
                                    {{ Str::of(auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}
                                </span>
                                <span class="hidden text-sm font-medium sm:inline">{{ auth()->user()?->name }}</span>
                                <svg class="h-4 w-4 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                            </button>

                            <div
                                x-show="menu"
                                @click.outside="menu = false"
                                x-transition.origin.top.right
                                class="glass absolute right-0 z-30 mt-2 w-56 overflow-hidden p-2"
                                style="display: none;"
                            >
                                <div class="px-3 py-2">
                                    <p class="truncate text-sm font-medium">{{ auth()->user()?->name }}</p>
                                    <p class="truncate text-xs text-faint">{{ auth()->user()?->email }}</p>
                                </div>
                                <div class="neon-divider my-1"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-muted transition hover:bg-violet-soft hover:text-ink">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0 4-4m-4 4 4 4m6-11h5a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-5"/></svg>
                                        {{ __('Log out') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
