<div>
    <x-ui.glass-card class="h-full">
        <h3 class="font-display text-lg font-semibold tracking-tight">Analyse de performance</h3>
        <p class="mt-0.5 text-sm text-muted">Allure moyenne et fréquence cardiaque.</p>

        <div class="mt-5 rounded-2xl border border-hairline p-4">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Allure moyenne</p>
            <div class="mt-2 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold text-violet">
                    @php
                        $paceSeconds = (int) round($averagePace);
                        $paceMinutes = intdiv($paceSeconds, 60);
                        $paceRest = $paceSeconds % 60;
                    @endphp
                    {{ $averagePace > 0 ? sprintf('%d:%02d', $paceMinutes, $paceRest) : '—' }}
                </span>
                <span class="text-sm text-muted">min / km</span>
            </div>
        </div>

        <div class="mt-5">
            <p class="mb-2 text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Fréquence cardiaque moyenne</p>

            @if (count($heartRates) > 0)
                <div wire:ignore>
                    <div
                        x-data
                        x-apexchart="{
                            chart: { type: 'line', height: 200, sparkline: { enabled: false } },
                            stroke: { curve: 'smooth', width: 2 },
                            colors: ['#9d6bff'],
                            dataLabels: { enabled: false },
                            xaxis: { categories: @js($labels), labels: { style: { colors: '#7b829a' } } },
                            yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => v.toFixed(0) + ' bpm' } },
                            series: [{ name: 'FC moyenne', data: @js($heartRates) }],
                        }"
                        wire:key="hr-{{ count($heartRates) }}"
                    ></div>
                </div>
            @else
                <p class="py-10 text-center text-sm text-faint">Aucune donnée cardio disponible.</p>
            @endif
        </div>
    </x-ui.glass-card>
</div>
