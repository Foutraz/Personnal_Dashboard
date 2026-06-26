<div>
    <x-slot:header>Météo &amp; Moto</x-slot:header>

    @php
        $accentText = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'];
        $accentStroke = ['cyan' => '#2ff3ff', 'violet' => '#9d6bff', 'lime' => '#c5ff4a'];
        $accentBadge = [
            'cyan' => 'border-cyan/40 bg-cyan-soft text-cyan',
            'violet' => 'border-violet/40 bg-violet-soft text-violet',
            'lime' => 'border-lime/40 bg-lime-soft text-lime',
        ];
    @endphp

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-violet">Module Météo &amp; Moto</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Le bon moment pour <span class="text-violet">rouler</span>.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Score « Moto Friendly » calculé depuis la météo réelle, créneaux favorables à venir et carnet de sorties.
            </p>
        </div>
    </section>

    @unless ($configured)
        <section class="reveal mt-8" style="animation-delay: 0.05s;">
            <x-ui.glass-card>
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-violet/40 bg-violet-soft text-violet">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.3 2.5 18a1.5 1.5 0 0 0 1.3 2.2h16.4A1.5 1.5 0 0 0 21.5 18L13.7 4.3a1.5 1.5 0 0 0-2.6 0Z"/></svg>
                        </span>
                        <div>
                            <h3 class="font-display text-base font-semibold">Météo non disponible</h3>
                            <p class="mt-0.5 text-sm text-muted">Renseignez <code class="text-violet">OPENWEATHER_API_KEY</code> pour configurer la clé météo et activer le score.</p>
                        </div>
                    </div>
                </div>
            </x-ui.glass-card>
        </section>
    @endunless

    @if ($configured && $condition)
        <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
            <x-ui.glass-card class="reveal flex flex-col items-center justify-center" style="animation-delay: 0.05s;">
                <p class="text-[0.7rem] font-medium uppercase tracking-[0.25em] text-faint">Score Moto Friendly</p>

                <div
                    x-data="{ score: {{ $condition['score'] }} }"
                    x-init="
                        const circ = 2 * Math.PI * 80;
                        const arc = $refs.arc;
                        arc.style.strokeDasharray = circ;
                        arc.style.strokeDashoffset = circ;
                        if (window.Motion) {
                            window.Motion.animate(0, score, {
                                duration: 1.4,
                                easing: [0.22, 1, 0.36, 1],
                                onUpdate: (v) => {
                                    arc.style.strokeDashoffset = circ - (circ * v / 100);
                                    $refs.value.textContent = Math.round(v);
                                },
                            });
                        } else {
                            arc.style.strokeDashoffset = circ - (circ * score / 100);
                            $refs.value.textContent = score;
                        }
                    "
                    class="relative mt-5 grid place-items-center"
                >
                    <svg class="h-52 w-52 -rotate-90" viewBox="0 0 200 200">
                        <circle cx="100" cy="100" r="80" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="14" />
                        <circle
                            x-ref="arc"
                            cx="100" cy="100" r="80" fill="none"
                            stroke="{{ $accentStroke[$condition['accent']] ?? $accentStroke['cyan'] }}"
                            stroke-width="14" stroke-linecap="round"
                            style="filter: drop-shadow(0 0 10px {{ $accentStroke[$condition['accent']] ?? $accentStroke['cyan'] }});"
                        />
                    </svg>
                    <div class="absolute flex flex-col items-center">
                        <span x-ref="value" class="font-display text-5xl font-bold {{ $accentText[$condition['accent']] ?? $accentText['cyan'] }}">0</span>
                        <span class="text-xs uppercase tracking-[0.2em] text-faint">/ 100</span>
                    </div>
                </div>

                <span class="mt-5 rounded-full border px-4 py-1.5 text-sm font-semibold {{ $accentBadge[$condition['accent']] ?? $accentBadge['cyan'] }}">
                    {{ $condition['label'] }}
                </span>

                <ul class="mt-5 flex w-full flex-col gap-1.5">
                    @foreach ($condition['reasons'] as $reason)
                        <li class="flex items-center gap-2 text-xs text-muted">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ str_replace('text-', 'bg-', $accentText[$condition['accent']] ?? 'text-cyan') }}"></span>
                            {{ $reason }}
                        </li>
                    @endforeach
                </ul>
            </x-ui.glass-card>

            <div class="xl:col-span-2 flex flex-col gap-6">
                @if ($current)
                    <x-ui.glass-card class="reveal" style="animation-delay: 0.1s;">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-display text-lg font-semibold tracking-tight">{{ $locationLabel }}</h3>
                                <p class="mt-0.5 text-sm capitalize text-muted">{{ $current->description }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-display text-3xl font-bold text-cyan">{{ number_format($current->temp, 0) }}°</p>
                                <p class="text-xs text-faint">ressenti {{ number_format($current->feelsLike, 0) }}°</p>
                            </div>
                        </div>
                        <div class="mt-5 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-xl border border-hairline bg-surface/50 py-3">
                                <p class="text-[0.65rem] uppercase tracking-wide text-faint">Vent</p>
                                <p class="mt-1 text-sm font-semibold text-ink">{{ number_format($current->windSpeed * 3.6, 0) }} km/h</p>
                            </div>
                            <div class="rounded-xl border border-hairline bg-surface/50 py-3">
                                <p class="text-[0.65rem] uppercase tracking-wide text-faint">Humidité</p>
                                <p class="mt-1 text-sm font-semibold text-ink">{{ $current->humidity }} %</p>
                            </div>
                            <div class="rounded-xl border border-hairline bg-surface/50 py-3">
                                <p class="text-[0.65rem] uppercase tracking-wide text-faint">Visibilité</p>
                                <p class="mt-1 text-sm font-semibold text-ink">{{ $current->visibility !== null ? number_format($current->visibility / 1000, 1) . ' km' : '—' }}</p>
                            </div>
                        </div>
                    </x-ui.glass-card>
                @endif

                <x-ui.glass-card class="reveal" style="animation-delay: 0.15s;">
                    <h3 class="font-display text-lg font-semibold tracking-tight">Prévisions horaires</h3>
                    <p class="mt-0.5 text-sm text-muted">Score moto pour chaque créneau de 3 h.</p>

                    <div class="mt-4 flex gap-2.5 overflow-x-auto pb-2">
                        @foreach ($hourly as $slot)
                            <div class="flex min-w-[4.5rem] shrink-0 flex-col items-center gap-1.5 rounded-2xl border border-hairline bg-surface/50 px-3 py-3">
                                <span class="text-[0.65rem] uppercase tracking-wide text-faint">{{ $slot['label'] }}</span>
                                <span class="text-sm font-semibold text-ink">{{ $slot['temp'] }}°</span>
                                <span class="text-[0.65rem] text-cyan">{{ $slot['pop'] }}% 🌧</span>
                                <span class="mt-1 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold {{ $accentBadge[$slot['accent']] ?? $accentBadge['cyan'] }}">{{ $slot['score'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if (count($hourly) > 0)
                        <div wire:ignore class="mt-4">
                            <div
                                x-data
                                x-apexchart="{
                                    chart: { type: 'area', height: 180, toolbar: { show: false }, sparkline: { enabled: false } },
                                    series: [
                                        { name: 'Température (°C)', data: @js(array_map(fn ($s) => $s['temp'], $hourly)) },
                                        { name: 'Pluie (%)', data: @js(array_map(fn ($s) => $s['pop'], $hourly)) },
                                    ],
                                    xaxis: { categories: @js(array_map(fn ($s) => $s['label'], $hourly)), labels: { style: { colors: '#5d5e78' } } },
                                    yaxis: { labels: { style: { colors: '#5d5e78' } } },
                                    colors: ['#2ff3ff', '#9d6bff'],
                                    fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                                    stroke: { curve: 'smooth', width: 2 },
                                    dataLabels: { enabled: false },
                                    grid: { borderColor: 'rgba(255,255,255,0.06)' },
                                    legend: { labels: { colors: '#9a9bb4' } },
                                    tooltip: { theme: 'dark' },
                                }"
                                wire:key="forecast-chart-{{ $locationLabel }}"
                            ></div>
                        </div>
                    @endif
                </x-ui.glass-card>
            </div>
        </section>

        <section class="reveal mt-8" style="animation-delay: 0.2s;">
            <x-ui.glass-card>
                <h3 class="font-display text-lg font-semibold tracking-tight">Créneaux favorables</h3>
                <p class="mt-0.5 text-sm text-muted">Les meilleures fenêtres pour une sortie à venir.</p>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @forelse ($slots as $slot)
                        <div class="flex flex-col gap-2 rounded-2xl border px-4 py-4 {{ $accentBadge[$slot['accent']] ?? $accentBadge['cyan'] }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-semibold">{{ $slot['label'] }}</span>
                                <span class="font-display text-lg font-bold">{{ $slot['score'] }}</span>
                            </div>
                            <p class="text-xs text-muted">
                                {{ \Illuminate\Support\Carbon::parse($slot['starts_at'])->translatedFormat('D d M, H\h') }}
                                →
                                {{ \Illuminate\Support\Carbon::parse($slot['ends_at'])->format('H\h') }}
                            </p>
                        </div>
                    @empty
                        <p class="col-span-full py-8 text-center text-sm text-faint">Aucun créneau favorable dans les prochaines prévisions.</p>
                    @endforelse
                </div>
            </x-ui.glass-card>
        </section>
    @endif

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style="animation-delay: 0.25s;">
        <x-ui.stat-tile label="Sorties" :value="(string) $stats['count']" accent="violet" trend="Carnet de trajets moto" />
        <x-ui.stat-tile label="Distance totale" :value="number_format($stats['distance'], 0, ',', ' ')" unit="km" accent="cyan" trend="Cumulé sur vos sorties" />
        <x-ui.stat-tile label="Temps de selle" :value="number_format($stats['duration'] / 3600, 1, ',', ' ')" unit="h" accent="lime" trend="Durée cumulée" />
        <x-ui.stat-tile label="Vitesse moyenne" :value="number_format($stats['average_speed'], 0)" unit="km/h" accent="violet" trend="Sur l'ensemble des sorties" />
    </section>

    <section class="mt-6">
        <x-ui.glass-card>
            <h3 class="font-display text-lg font-semibold tracking-tight">Enregistrer une sortie</h3>
            <p class="mt-0.5 text-sm text-muted">Liberty Rider n'expose pas d'API publique : saisissez vos trajets manuellement.</p>

            <form wire:submit="logRide" class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-start">
                <div class="lg:col-span-3">
                    <input type="text" wire:model="rideTitle" placeholder="Titre (ex. Cols alpins)" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-violet/40 focus:outline-none" />
                    @error('rideTitle') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-3">
                    <input type="datetime-local" wire:model="rideStartedAt" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition focus:border-violet/40 focus:outline-none" />
                    @error('rideStartedAt') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-2">
                    <input type="number" min="1" wire:model="rideDuration" placeholder="Durée (min)" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-violet/40 focus:outline-none" />
                    @error('rideDuration') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-2">
                    <input type="number" step="0.01" min="0" wire:model="rideDistance" placeholder="Distance (km)" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-violet/40 focus:outline-none" />
                    @error('rideDistance') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-2">
                    <x-ui.neon-button type="submit" variant="violet" class="w-full">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        Ajouter
                    </x-ui.neon-button>
                </div>
            </form>

            <div wire:loading.delay.class="opacity-40" class="mt-6 flex flex-col gap-2.5 transition-opacity">
                @forelse ($rides as $ride)
                    <div
                        wire:key="ride-{{ $ride->id }}"
                        x-data
                        x-init="$el.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 320, easing: 'cubic-bezier(0.22,1,0.36,1)' })"
                        class="group flex items-center gap-3.5 rounded-2xl border border-hairline bg-surface/60 px-4 py-3.5 transition-all duration-300 hover:border-white/15"
                    >
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-violet/40 bg-violet-soft text-violet">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 16a3 3 0 1 0 0 .01M19 16a3 3 0 1 0 0 .01M7 16h7l3-5h2m-12 5-2-6H3m4 6 2-6h6l2 3"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $ride->title }}</p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-faint">
                                <span>{{ $ride->started_at->translatedFormat('d/m/Y H\h') }}</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ number_format((int) round($ride->duration / 60)) }} min</span>
                                @if ($ride->weather_label)
                                    <span aria-hidden="true">•</span>
                                    <span class="text-lime">{{ $ride->weather_label }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-semibold text-cyan">{{ number_format((float) $ride->distance, 0, ',', ' ') }} km</span>
                        <button
                            type="button"
                            wire:click="deleteRide('{{ $ride->id }}')"
                            wire:confirm="Supprimer cette sortie ?"
                            class="shrink-0 text-faint opacity-0 transition hover:text-violet group-hover:opacity-100"
                            aria-label="Supprimer la sortie"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg>
                        </button>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-faint">Aucune sortie enregistrée. Ajoutez votre première balade.</p>
                @endforelse
            </div>
        </x-ui.glass-card>
    </section>
</div>
