<div>
    <x-slot:header>Cartes &amp; Exploration</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Module Cartes &amp; Exploration</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre territoire, <span class="text-cyan">cartographié</span> trajet après trajet.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Chaque sortie synchronisée trace une ligne lumineuse sur la carte. La grille verte révèle la
                couverture géographique cumulée et le pourcentage d'exploration de chaque région.
            </p>
        </div>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style="animation-delay: 0.05s;">
        <x-ui.stat-tile
            label="Distance cartographiée"
            :value="number_format($totalDistanceKm, 1, ',', ' ')"
            unit="km"
            accent="cyan"
            trend="Trajets tracés"
        />
        <x-ui.stat-tile
            label="Cellules explorées"
            :value="number_format($distinctCells, 0, ',', ' ')"
            accent="lime"
            trend="Zones distinctes"
        />
        <x-ui.stat-tile
            label="Surface couverte"
            :value="number_format($areaKm2, 1, ',', ' ')"
            unit="km²"
            accent="violet"
            trend="Estimation grille"
        />
        <x-ui.glass-card hover padding="p-5">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Exploration région</p>
            <div class="mt-4 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold text-cyan">{{ number_format($selectedPercentage, 2, ',', ' ') }}</span>
                <span class="text-sm text-muted">%</span>
            </div>
            <select
                wire:model.live="selectedRegion"
                class="mt-3 w-full rounded-xl border border-hairline bg-surface px-3 py-2 text-xs text-ink transition focus:border-cyan/40 focus:outline-none"
            >
                @foreach ($regions as $region)
                    <option value="{{ $region['key'] }}">{{ $region['label'] }}</option>
                @endforeach
            </select>
        </x-ui.glass-card>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3" style="animation-delay: 0.1s;">
        <div class="xl:col-span-2">
            <x-ui.glass-card padding="p-0" class="overflow-hidden">
                <div class="flex items-center justify-between gap-4 px-6 py-4">
                    <div>
                        <h3 class="font-display text-lg font-semibold tracking-tight">Carte d'exploration</h3>
                        <p class="mt-0.5 text-xs text-muted">Trajets en cyan, couverture en vert lime.</p>
                    </div>
                    <button
                        type="button"
                        wire:click="recalculate"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-xl border border-cyan/30 bg-cyan-soft px-4 py-2 text-sm font-medium text-cyan transition hover:border-cyan/60 disabled:opacity-50"
                    >
                        <svg wire:loading.remove wire:target="recalculate" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-3l3 3M19 15a7 7 0 0 1-12 3l-3-3"/></svg>
                        <svg wire:loading wire:target="recalculate" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/></svg>
                        Recalculer la couverture
                    </button>
                </div>

                <div wire:ignore>
                    <div
                        x-data
                        x-leaflet="{ routes: @js($routes), cells: @js($cellPoints) }"
                        class="h-[28rem] w-full sm:h-[34rem]"
                        style="background: var(--color-void);"
                    ></div>
                </div>
            </x-ui.glass-card>
        </div>

        <x-ui.glass-card>
            <h3 class="font-display text-lg font-semibold tracking-tight">Régions parcourues</h3>
            <p class="mt-0.5 text-xs text-muted">Part de chaque région déjà explorée.</p>

            <div class="mt-5 flex flex-col gap-4">
                @forelse ($regionBreakdown as $region)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-ink">{{ $region['label'] }}</span>
                            <span class="text-muted">{{ number_format($region['percentage'], 2, ',', ' ') }}%</span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                            <div
                                class="h-full rounded-full bg-lime"
                                style="width: {{ min($region['percentage'], 100) }}%; box-shadow: 0 0 12px rgba(197,255,74,0.7);"
                            ></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-faint">Aucune région configurée.</p>
                @endforelse
            </div>

            @if ($distinctCells === 0)
                <div class="mt-6 rounded-xl border border-hairline bg-surface px-4 py-3 text-xs text-muted">
                    Aucune couverture pour l'instant. Synchronisez des activités Strava puis lancez
                    <span class="text-cyan">Recalculer la couverture</span>.
                </div>
            @endif
        </x-ui.glass-card>
    </section>
</div>
