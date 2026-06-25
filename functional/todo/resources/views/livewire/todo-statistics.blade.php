<div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile
            label="Taux de complétion"
            :value="number_format($completionRate, 1, ',', ' ')"
            unit="%"
            accent="lime"
            :trend="$done.' tâche(s) terminée(s)'"
        />
        <x-ui.stat-tile
            label="À faire"
            :value="(string) $pending"
            accent="cyan"
            trend="En attente de traitement"
        />
        <x-ui.stat-tile
            label="En cours"
            :value="(string) $inProgress"
            accent="violet"
            trend="Travail en progression"
        />
        <x-ui.stat-tile
            label="Total"
            :value="(string) $total"
            accent="cyan"
            trend="Toutes priorités confondues"
        />
    </div>

    <x-ui.glass-card class="mt-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-display text-lg font-semibold tracking-tight">Répartition des statuts</h3>
                <p class="mt-0.5 text-sm text-muted">Vue d'ensemble de l'avancement.</p>
            </div>
        </div>

        @if ($total === 0)
            <p class="mt-6 text-center text-sm text-faint">Aucune tâche à analyser pour le moment.</p>
        @else
            <div wire:loading.delay class="mt-6 h-64 animate-pulse rounded-2xl bg-white/5"></div>

            <div wire:loading.remove class="mt-4" wire:ignore>
                <div
                    x-data
                    x-apexchart="{
                        chart: { type: 'donut', height: 256 },
                        labels: @js($statusLabels),
                        series: @js($statusValues),
                        legend: { position: 'bottom', labels: { colors: '#9a9bb4' } },
                        plotOptions: { pie: { donut: { size: '70%' } } },
                        dataLabels: { enabled: false },
                        stroke: { width: 0 },
                    }"
                    wire:key="status-breakdown-{{ $total }}-{{ implode('-', $statusValues) }}"
                ></div>
            </div>
        @endif
    </x-ui.glass-card>
</div>
