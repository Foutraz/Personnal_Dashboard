<x-ui.glass-card>
    <div class="flex items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-lime-soft text-lime">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17l5-5 4 4 8-8M21 8v5h-5"/></svg>
        </span>
        <div>
            <h3 class="font-display text-lg font-semibold tracking-tight">Simulateur DCA</h3>
            <p class="text-sm text-muted">Versements programmés et intérêts composés.</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <div class="flex items-center justify-between text-sm">
                <label class="text-muted">Montant par versement</label>
                <span class="font-display font-semibold text-lime">{{ number_format($finalInvested > 0 ? $this->periodicAmount : $this->periodicAmount, 0, ',', ' ') }} €</span>
            </div>
            <input type="range" min="10" max="2000" step="10" wire:model.live="periodicAmount" class="mt-2 w-full accent-lime" />
        </div>

        <div>
            <div class="flex items-center justify-between text-sm">
                <label class="text-muted">Durée</label>
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

        <div>
            <label class="text-sm text-muted">Fréquence</label>
            <div class="relative mt-2">
                <select wire:model.live="frequency" class="w-full appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink focus:border-lime/40 focus:outline-none">
                    @foreach ($frequencies as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-3 gap-3 text-center">
        <div class="rounded-xl border border-hairline bg-surface/50 p-3">
            <p class="text-[0.65rem] uppercase tracking-wider text-faint">Investi</p>
            <p class="mt-1 font-display text-lg font-bold text-violet">{{ number_format($finalInvested, 0, ',', ' ') }} €</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface/50 p-3">
            <p class="text-[0.65rem] uppercase tracking-wider text-faint">Valeur</p>
            <p class="mt-1 font-display text-lg font-bold text-cyan">{{ number_format($finalValue, 0, ',', ' ') }} €</p>
        </div>
        <div class="rounded-xl border border-hairline bg-surface/50 p-3">
            <p class="text-[0.65rem] uppercase tracking-wider text-faint">Gain</p>
            <p class="mt-1 font-display text-lg font-bold text-lime">+{{ number_format($finalGain, 0, ',', ' ') }} €</p>
        </div>
    </div>

    <div wire:loading.delay.flex class="mt-6 hidden h-64 animate-pulse rounded-2xl bg-white/5"></div>

    <div wire:loading.remove class="mt-4" wire:ignore>
        <div
            x-data
            x-apexchart="{
                chart: { type: 'area', height: 256, stacked: false },
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.04, stops: [0, 90, 100] } },
                dataLabels: { enabled: false },
                xaxis: { categories: @js($labels), labels: { style: { colors: '#7b829a' } } },
                yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => Math.round(v / 1000) + 'k €' } },
                legend: { labels: { colors: '#9a9bb4' } },
                colors: ['#c5ff4a', '#9d6bff'],
                series: [
                    { name: 'Valeur', data: @js($valueSeries) },
                    { name: 'Investi', data: @js($investedSeries) },
                ],
            }"
            wire:key="dca-{{ $years }}-{{ $frequency }}-{{ (int) $finalValue }}"
        ></div>
    </div>
</x-ui.glass-card>
