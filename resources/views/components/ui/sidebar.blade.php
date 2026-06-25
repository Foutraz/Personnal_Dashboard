@php
    $navItems = [
        ['label' => 'Vue d\'ensemble', 'route' => 'dashboard', 'available' => true, 'icon' => 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
        ['label' => 'Sport', 'route' => 'sport', 'available' => true, 'icon' => 'M4 7h3l2-3h6l2 3h3M5 7v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7M9 13a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z'],
        ['label' => 'Météo & Moto', 'available' => false, 'icon' => 'M3 15a4 4 0 0 0 4 4h9a4 4 0 0 0 0-8 6 6 0 0 0-11.7-1.8A4 4 0 0 0 3 15Z'],
        ['label' => 'Finance', 'route' => 'finance', 'available' => true, 'icon' => 'M3 17l5-5 4 4 8-8M21 8v5h-5'],
        ['label' => 'Échéances', 'route' => 'recurring-expenses', 'available' => true, 'icon' => 'M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z'],
        ['label' => 'Planning', 'available' => false, 'icon' => 'M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        ['label' => 'To-Do', 'route' => 'todo', 'available' => true, 'icon' => 'M9 11l3 3 8-8M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
        ['label' => 'Objectifs', 'route' => 'goals', 'available' => true, 'icon' => 'M12 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0 0a9 9 0 1 1 0 18 9 9 0 0 1 0-18Zm0 6v0M12 3v3'],
        ['label' => 'Cartes', 'route' => 'exploration', 'available' => true, 'icon' => 'M9 6 3 4v14l6 2 6-2 6 2V6l-6-2-6 2Zm0 0v14m6-12v14'],
        ['label' => 'Intégrations', 'route' => 'integrations', 'available' => true, 'icon' => 'M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1'],
    ];
@endphp

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
        @foreach ($navItems as $item)
            @php
                $isAvailable = $item['available'];
                $itemRoute = $item['route'] ?? null;
                $isActive = $itemRoute && request()->routeIs($itemRoute);
            @endphp

            <x-ui.nav-link
                :href="$isAvailable && $itemRoute ? route($itemRoute) : '#'"
                :active="$isActive"
                :disabled="! $isAvailable"
            >
                <x-slot:icon>
                    <svg class="h-[1.15rem] w-[1.15rem]" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                </x-slot:icon>
                {{ $item['label'] }}
            </x-ui.nav-link>
        @endforeach
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
