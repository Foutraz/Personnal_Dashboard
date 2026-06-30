<aside
    class="glass fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col rounded-none border-y-0 border-l-0 px-5 py-6 transition-transform duration-300 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    style="--radius-glass: 0;"
>
    <div class="flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-cyan-soft font-display text-lg font-bold text-cyan text-glow-cyan">P</span>
            <span class="font-display text-base font-bold tracking-tight">Personal<span class="text-cyan">Deck</span></span>
        </a>
        <button @click="sidebarOpen = false" class="text-faint transition hover:text-ink lg:hidden" aria-label="Close navigation">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>

    <p class="mt-9 px-3.5 text-[0.65rem] font-semibold uppercase tracking-[0.3em] text-faint">Modules</p>

    <nav class="mt-3 flex flex-1 flex-col gap-1">
        <x-ui.nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            <x-slot:icon>
                <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z"/></svg>
            </x-slot:icon>
            Vue d'ensemble
        </x-ui.nav-link>

        @foreach ($moduleNavItems as $item)
            <x-ui.nav-link :href="route($item->route)" :active="request()->routeIs($item->route)">
                <x-slot:icon>
                    <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item->icon }}"/></svg>
                </x-slot:icon>
                {{ $item->label }}
            </x-ui.nav-link>
        @endforeach

        <x-ui.nav-link :href="route('integrations')" :active="request()->routeIs('integrations')">
            <x-slot:icon>
                <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
            </x-slot:icon>
            Intégrations
        </x-ui.nav-link>
    </nav>

    <div class="neon-divider my-4"></div>

    <div class="glass flex items-center gap-3 p-3" style="--radius-glass: 16px;">
        <span class="grid h-9 w-9 place-items-center rounded-full bg-violet-soft font-display text-sm font-semibold text-violet">
            {{ Str::of(auth()->user()?->name ?? 'U')->substr(0, 1)->upper() }}
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-medium">{{ auth()->user()?->name }}</p>
            <p class="truncate text-xs text-faint">{{ auth()->user()?->email }}</p>
        </div>
    </div>
</aside>
