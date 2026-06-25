<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <x-ui.glass-card class="xl:col-span-2">
        <h3 class="font-display text-lg font-semibold tracking-tight">Valeur vs capital investi</h3>
        <p class="mt-0.5 text-sm text-muted">Comparaison par position, en euros.</p>

        <div wire:loading.delay class="mt-6 h-72 animate-pulse rounded-2xl bg-white/5"></div>

        @if (count($labels) === 0)
            <p class="mt-10 py-12 text-center text-sm text-faint">Ajoutez un prix actuel à vos positions pour visualiser leur performance.</p>
        @else
            <div wire:loading.remove class="mt-4" wire:ignore>
                <div
                    x-data
                    x-apexchart="{
                        chart: { type: 'bar', height: 288 },
                        plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
                        dataLabels: { enabled: false },
                        xaxis: { categories: @js($labels), labels: { style: { colors: '#7b829a' } } },
                        yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => v.toFixed(0) + ' €' } },
                        legend: { labels: { colors: '#9a9bb4' } },
                        colors: ['#9d6bff', '#2ff3ff'],
                        series: [
                            { name: 'Investi', data: @js($invested) },
                            { name: 'Valeur', data: @js($values) },
                        ],
                    }"
                    wire:key="perf-{{ count($values) }}-{{ array_sum($values) }}"
                ></div>
            </div>
        @endif
    </x-ui.glass-card>

    <x-ui.glass-card>
        <h3 class="font-display text-lg font-semibold tracking-tight">Allocation</h3>
        <p class="mt-0.5 text-sm text-muted">Répartition par valeur de marché.</p>

        <div wire:loading.delay class="mt-6 h-72 animate-pulse rounded-2xl bg-white/5"></div>

        @if (count($allocationValues) === 0)
            <p class="mt-10 py-12 text-center text-sm text-faint">Aucune répartition disponible.</p>
        @else
            <div wire:loading.remove class="mt-4" wire:ignore>
                <div
                    x-data
                    x-apexchart="{
                        chart: { type: 'donut', height: 288 },
                        labels: @js($allocationLabels),
                        dataLabels: { enabled: false },
                        legend: { position: 'bottom', labels: { colors: '#9a9bb4' } },
                        stroke: { width: 0 },
                        plotOptions: { pie: { donut: { size: '68%' } } },
                        colors: ['#2ff3ff', '#9d6bff', '#c5ff4a', '#5d5e78', '#f0abfc'],
                        series: @js($allocationValues),
                    }"
                    wire:key="alloc-{{ count($allocationValues) }}-{{ array_sum($allocationValues) }}"
                ></div>
            </div>
        @endif
    </x-ui.glass-card>
</div>
