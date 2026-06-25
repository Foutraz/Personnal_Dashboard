<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile
            label="Distance totale"
            :value="number_format($totalDistance / 1000, 1, ',', ' ')"
            unit="km"
            accent="cyan"
            :trend="$count.' activités enregistrées'"
        />
        <x-ui.stat-tile
            label="Dénivelé positif"
            :value="number_format($totalElevation, 0, ',', ' ')"
            unit="m"
            accent="violet"
            trend="Cumul de toutes vos sorties"
        />
        <x-ui.stat-tile
            label="Temps en mouvement"
            :value="floor($totalMovingTime / 3600).'h'.str_pad((string) floor(($totalMovingTime % 3600) / 60), 2, '0', STR_PAD_LEFT)"
            accent="lime"
            trend="Durée effective d'effort"
        />
        <x-ui.stat-tile
            label="Activités"
            :value="(string) $count"
            accent="cyan"
            trend="Toutes disciplines confondues"
        />
    </div>

    <x-ui.glass-card class="mt-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-display text-lg font-semibold tracking-tight">Évolution de la distance</h3>
                <p class="mt-0.5 text-sm text-muted">Cumul par période, en kilomètres.</p>
            </div>

            <div class="inline-flex rounded-xl border border-hairline p-1">
                @foreach ($periods as $option)
                    <button
                        type="button"
                        wire:click="$set('period', '{{ $option->value }}')"
                        @class([
                            'rounded-lg px-3.5 py-1.5 text-xs font-medium transition',
                            'bg-cyan-soft text-cyan' => $period === $option->value,
                            'text-muted hover:text-ink' => $period !== $option->value,
                        ])
                    >
                        {{ $option->label() }}
                    </button>
                @endforeach
            </div>
        </div>

        <div wire:loading.delay class="mt-6 h-72 animate-pulse rounded-2xl bg-white/5"></div>

        <div wire:loading.remove class="mt-4" wire:ignore>
            <div
                x-data
                x-apexchart="{
                    chart: { type: 'area', height: 288 },
                    stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] } },
                    dataLabels: { enabled: false },
                    xaxis: { categories: @js($evolutionLabels), labels: { style: { colors: '#7b829a' } } },
                    yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => v.toFixed(0) + ' km' } },
                    series: [{ name: 'Distance', data: @js($evolutionValues) }],
                }"
                wire:key="evolution-{{ $period }}-{{ count($evolutionValues) }}"
            ></div>
        </div>

        @if (count($evolutionValues) === 0)
            <p class="mt-4 text-center text-sm text-faint">Aucune donnée à afficher pour le moment.</p>
        @endif
    </x-ui.glass-card>
</div>
