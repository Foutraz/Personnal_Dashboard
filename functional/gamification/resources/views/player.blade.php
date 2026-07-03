<div>
    <x-slot:header>Joueur</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-violet">Module Joueur — Gamification</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre vie, <span class="text-violet text-glow-violet">convertie en XP</span>.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Chaque activité sportive, tâche accomplie, pesée, sortie moto, mois d'épargne positive et zone explorée
                alimente votre progression. Battez-vous contre vous-même.
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        <x-ui.glass-card hover class="relative overflow-hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-violet-soft opacity-60 blur-3xl"></div>

            <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:items-center">
                <x-gamification::level-ring :level="$level" :percentage="$levelPercentage" />

                <div class="flex flex-col gap-1 text-center sm:text-left">
                    <p class="font-display text-2xl font-semibold tracking-tight">{{ number_format($totalXp, 0, ',', ' ') }} XP</p>
                    <p class="text-sm text-muted">
                        Encore <span class="font-semibold text-violet">{{ number_format($remainingXp, 0, ',', ' ') }} XP</span>
                        avant le niveau {{ $level + 1 }}.
                    </p>
                    <div class="mt-2 h-1.5 w-full min-w-56 overflow-hidden rounded-full bg-surface-2">
                        <div
                            class="h-full rounded-full bg-violet"
                            style="width: {{ (int) min($levelPercentage, 100) }}%; box-shadow: 0 0 12px rgba(157,107,255,0.7);"
                        ></div>
                    </div>
                </div>
            </div>
        </x-ui.glass-card>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2" style="animation-delay: 0.1s;">
        <x-ui.stat-tile
            label="XP cette semaine"
            :value="number_format($weeklyXp, 0, ',', ' ')"
            unit="XP"
            accent="cyan"
            trend="7 derniers jours"
        />
        <x-ui.stat-tile
            label="XP ce mois-ci"
            :value="number_format($monthlyXp, 0, ',', ' ')"
            unit="XP"
            accent="lime"
            trend="Depuis le 1er du mois"
        />
    </section>

    <x-ui.glass-card class="mt-8">
        <h3 class="font-display text-lg font-semibold tracking-tight">XP par jour</h3>
        <p class="mt-0.5 text-sm text-muted">Vos gains d'expérience sur les 30 derniers jours.</p>

        <div class="mt-4" wire:ignore>
            <div
                x-data
                x-apexchart="{
                    chart: { type: 'area', height: 220, sparkline: { enabled: false }, toolbar: { show: false } },
                    stroke: { curve: 'smooth', width: 2 },
                    colors: ['#9d6bff'],
                    fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: 'rgba(255,255,255,0.06)' },
                    xaxis: { categories: @js($chartLabels), labels: { show: false }, axisTicks: { show: false } },
                    yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => v.toFixed(0) + ' XP' } },
                    tooltip: { theme: 'dark' },
                    series: [{ name: 'XP', data: @js($chartValues) }],
                }"
                wire:key="xp-daily-{{ count($chartValues) }}"
            ></div>
        </div>
    </x-ui.glass-card>

    <section class="mt-8">
        <h3 class="font-display text-lg font-semibold tracking-tight">XP par domaine</h3>

        @php
            $accents = [
                'cyan' => ['text' => 'text-cyan', 'bg' => 'bg-cyan-soft'],
                'violet' => ['text' => 'text-violet', 'bg' => 'bg-violet-soft'],
                'lime' => ['text' => 'text-lime', 'bg' => 'bg-lime-soft'],
            ];
        @endphp

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($domains as $domain)
                @php $accent = $accents[$domain->color()] ?? $accents['cyan']; @endphp
                <x-ui.glass-card hover padding="p-5">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $accent['bg'] }} {{ $accent['text'] }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="{{ $domain->icon() }}" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $domain->label() }}</p>
                            <p class="font-display text-xl font-bold {{ $accent['text'] }}">
                                {{ number_format($domainTotals[$domain->value] ?? 0, 0, ',', ' ') }} <span class="text-sm font-normal text-muted">XP</span>
                            </p>
                        </div>
                    </div>
                </x-ui.glass-card>
            @endforeach
        </div>
    </section>
</div>
