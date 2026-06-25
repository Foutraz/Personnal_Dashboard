<x-ui.glass-card>
    <div class="flex items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-violet-soft text-violet">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </span>
        <div>
            <h3 class="font-display text-lg font-semibold tracking-tight">Projection du portefeuille</h3>
            <p class="text-sm text-muted">À partir de {{ number_format($startingValue, 0, ',', ' ') }} € investis aujourd'hui.</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
        <div>
            <div class="flex items-center justify-between text-sm">
                <label class="text-muted">Versement mensuel</label>
                <span class="font-display font-semibold text-lime">{{ number_format($this->monthlyContribution, 0, ',', ' ') }} €</span>
            </div>
            <input type="range" min="0" max="2000" step="10" wire:model.live="monthlyContribution" class="mt-2 w-full accent-lime" />
        </div>

        <div>
            <div class="flex items-center justify-between text-sm">
                <label class="text-muted">Horizon</label>
                <span class="font-display font-semibold text-cyan">{{ $years }} ans</span>
            </div>
            <input type="range" min="1" max="40" step="1" wire:model.live="years" class="mt-2 w-full accent-cyan" />
        </div>

        <div>
            <div class="flex items-center justify-between text-sm">
                <label class="text-muted">Rendement annuel</label>
                <span class="font-display font-semibold text-violet">{{ number_format($annualReturnRate, 1, ',', ' ') }} %</span>
            </div>
            <input type="range" min="0" max="20" step="0.5" wire:model.live="annualReturnRate" class="mt-2 w-full accent-violet" />
        </div>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 text-center">
        <div class="rounded-xl border border-hairline bg-surface/50 p-3">
            <p class="text-[0.65rem] uppercase tracking-wider text-faint">Valeur projetée</p>
            <p class="mt-1 font-display text-lg font-bold text-cyan">{{ number_format($finalValue, 0, ',', ' ') }} €</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface/50 p-3">
            <p class="text-[0.65rem] uppercase tracking-wider text-faint">Gain cumulé</p>
            <p class="mt-1 font-display text-lg font-bold text-lime">+{{ number_format($finalGain, 0, ',', ' ') }} €</p>
        </div>
    </div>

    <div wire:loading.delay.flex class="mt-6 hidden h-64 animate-pulse rounded-2xl bg-white/5"></div>

    <div wire:loading.remove class="mt-4" wire:ignore>
        <div
            x-data
            x-apexchart="{
                chart: { type: 'area', height: 256 },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.04, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                xaxis: { categories: @js($labels), labels: { style: { colors: '#7b829a' } } },
                yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => Math.round(v / 1000) + 'k €' } },
                legend: { labels: { colors: '#9a9bb4' } },
                colors: ['#2ff3ff', '#9d6bff'],
                series: [
                    { name: 'Valeur', data: @js($valueSeries) },
                    { name: 'Versé', data: @js($contributedSeries) },
                ],
            }"
            wire:key="proj-{{ $years }}-{{ (int) $finalValue }}-{{ (int) $this->monthlyContribution }}"
        ></div>
    </div>
</x-ui.glass-card>
