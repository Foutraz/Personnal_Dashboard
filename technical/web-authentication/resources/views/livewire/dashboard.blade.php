<div>
    <x-slot:header>Dashboard</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Bonjour {{ auth()->user()?->name }}</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre <span class="text-cyan text-glow-cyan">command deck</span> personnel.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Tous vos modules réunis sur une seule surface. Connectez vos services et laissez le tableau de bord agréger sport, finance, planning et plus encore.
            </p>
        </div>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style="animation-delay: 0.1s;">
        <x-ui.stat-tile label="Modules disponibles" :value="$stats['modules_available']" :unit="'/ '.$stats['modules_total']" accent="cyan" trend="Modules actuellement accessibles." />
        <x-ui.stat-tile label="Intégrations" :value="$stats['integrations_count']" unit="connectée(s)" accent="violet" :trend="$stats['integration_labels']" />
        <x-ui.stat-tile label="Activités synchronisées" :value="$stats['activities_count']" accent="lime" trend="Depuis vos services connectés." />
        @if ($stats['last_activity'])
            <x-ui.stat-tile label="Dernière activité" :value="$stats['last_activity']->started_at->isoFormat('D MMM')" accent="cyan" :trend="$stats['last_activity']->name" />
        @else
            <x-ui.stat-tile label="Dernière activité" value="—" accent="cyan" trend="Aucune activité synchronisée." />
        @endif
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="font-display text-lg font-semibold tracking-tight">Modules</h3>
            <span class="text-xs text-faint">{{ $stats['modules_available'] }} / {{ $stats['modules_total'] }} disponibles</span>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($modules as $index => $module)
                <x-ui.module-card
                    :title="$module['title']"
                    :description="$module['description']"
                    :accent="$module['accent']"
                    :available="$module['available']"
                    :href="$module['href'] ?? null"
                    :delay="$index * 60"
                >
                    <x-slot:icon>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $module['icon'] }}"/></svg>
                    </x-slot:icon>
                </x-ui.module-card>
            @endforeach
        </div>
    </section>
</div>
