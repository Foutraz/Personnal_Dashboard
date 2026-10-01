<div>
    <x-slot:header>{{ __('gamification::player.title') }}</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-violet">{{ __('gamification::player.eyebrow') }}</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                {{ __('gamification::player.headline_lead') }} <span class="text-violet text-glow-violet">{{ __('gamification::player.headline_highlight') }}</span>.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                {{ __('gamification::player.intro') }}
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        <x-ui.glass-card hover class="relative overflow-hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-violet-soft opacity-60 blur-3xl"></div>

            <div class="relative flex flex-col items-center gap-6 sm:flex-row sm:items-center">
                <x-gamification::level-ring :level="$level" :percentage="$levelPercentage" />

                <div class="flex flex-col gap-1 text-center sm:text-left">
                    <p class="font-display text-2xl font-semibold tracking-tight">{{ __('gamification::player.xp_amount', ['xp' => number_format($totalXp, 0, ',', ' ')]) }}</p>
                    <p class="text-sm text-muted">
                        {{ __('gamification::player.remaining_lead') }} <span class="font-semibold text-violet">{{ __('gamification::player.xp_amount', ['xp' => number_format($remainingXp, 0, ',', ' ')]) }}</span>
                        {{ __('gamification::player.remaining_tail', ['level' => $level + 1]) }}
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
            :label="__('gamification::player.weekly_label')"
            :value="number_format($weeklyXp, 0, ',', ' ')"
            :unit="__('gamification::player.xp_unit')"
            accent="cyan"
            :trend="__('gamification::player.weekly_trend')"
        />
        <x-ui.stat-tile
            :label="__('gamification::player.monthly_label')"
            :value="number_format($monthlyXp, 0, ',', ' ')"
            :unit="__('gamification::player.xp_unit')"
            accent="lime"
            :trend="__('gamification::player.monthly_trend')"
        />
    </section>

    @if ($streakCards->isNotEmpty())
        <section class="mt-8" style="animation-delay: 0.15s;">
            <h3 class="font-display text-lg font-semibold tracking-tight">{{ __('gamification::player.streaks_title') }}</h3>
            <p class="mt-0.5 text-sm text-muted">{{ __('gamification::player.streaks_subtitle') }}</p>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($streakCards as $streakCard)
                    <x-ui.glass-card hover padding="p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $streakCard->domain->softBackgroundClass() }} {{ $streakCard->domain->textClass() }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="{{ $streakCard->domain->icon() }}" />
                                    </svg>
                                </span>
                                <div>
                                    <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $streakCard->domain->label() }}</p>
                                    <p class="font-display text-xl font-bold {{ $streakCard->countClass() }}">
                                        {{ $streakCard->currentCount }} <span class="text-sm font-normal text-muted">{{ __('gamification::player.day_unit') }}</span>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                @if ($streakCard->isAlive)
                                    <span class="text-lg" title="{{ __('gamification::player.streak_alive') }}">🔥</span>
                                @endif
                                <p class="text-xs text-faint">{{ __('gamification::player.streak_best', ['count' => $streakCard->bestCount]) }}</p>
                            </div>
                        </div>
                    </x-ui.glass-card>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8" style="animation-delay: 0.2s;">
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="font-display text-lg font-semibold tracking-tight">{{ __('gamification::badges.showcase.title') }}</h3>
            <p class="font-display text-lg font-bold text-violet text-glow-violet">
                {{ __('gamification::badges.showcase.counter', ['earned' => $badgesEarned, 'total' => $badgesTotal]) }}
            </p>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($badgeFamilies as $family)
                <x-ui.glass-card hover padding="p-5">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $family->domain->softBackgroundClass() }} {{ $family->domain->textClass() }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="{{ $family->domain->icon() }}" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $family->domain->label() }}</p>
                            <p class="font-display text-base font-semibold">{{ $family->name }}</p>
                        </div>
                    </div>

                    <ul class="mt-5 grid grid-cols-3 gap-2">
                        @foreach ($family->medals as $medal)
                            <li class="flex flex-col items-center gap-1.5 text-center" title="{{ $medal->description }}">
                                <span
                                    role="img"
                                    aria-label="{{ $medal->accessibleLabel() }}"
                                    class="grid h-12 w-12 place-items-center rounded-full border {{ $medal->frameClass() }}"
                                    style="--medal: {{ $medal->tier->accent() }};"
                                >
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 15a7 7 0 1 0 0-14 7 7 0 0 0 0 14ZM8.21 13.89 7 23l5-3 5 3-1.21-9.12" />
                                    </svg>
                                </span>
                                <span aria-hidden="true" class="text-[0.7rem] font-medium uppercase tracking-[0.18em] text-muted">{{ $medal->tier->label() }}</span>
                                <span aria-hidden="true" class="text-xs text-faint">{{ $medal->thresholdLabel }}</span>
                                <span class="sr-only">{{ $medal->description }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-5">
                        @unless ($family->isAwaitingUnlock())
                            <div
                                role="progressbar"
                                aria-label="{{ $family->name }}"
                                aria-valuenow="{{ $family->roundedPercentage() }}"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                class="h-1.5 w-full overflow-hidden rounded-full bg-surface-2 {{ $family->domain->textClass() }}"
                            >
                                <div
                                    class="h-full rounded-full bg-current"
                                    style="width: {{ $family->roundedPercentage() }}%; box-shadow: 0 0 10px currentColor;"
                                ></div>
                            </div>
                        @endunless

                        <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                            <span class="text-muted">{{ __('gamification::badges.showcase.current', ['value' => $family->currentLabel()]) }}</span>
                            @if ($family->isComplete())
                                <span class="font-medium {{ $family->domain->textClass() }}">{{ __('gamification::badges.showcase.completed') }}</span>
                            @elseif ($family->isAwaitingUnlock())
                                <span class="font-medium {{ $family->domain->textClass() }}">{{ __('gamification::badges.showcase.pending') }}</span>
                            @else
                                <span class="text-faint">{{ __('gamification::badges.showcase.next', ['threshold' => $family->nextLabel()]) }}</span>
                            @endif
                        </div>
                    </div>
                </x-ui.glass-card>
            @endforeach
        </div>
    </section>

    <x-ui.glass-card class="mt-8">
        <h3 class="font-display text-lg font-semibold tracking-tight">{{ __('gamification::player.daily_title') }}</h3>
        <p class="mt-0.5 text-sm text-muted">{{ __('gamification::player.daily_subtitle', ['days' => $dailySeriesDays]) }}</p>

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
                    yaxis: { labels: { style: { colors: '#7b829a' }, formatter: (v) => v.toFixed(0) + ' ' + @js(__('gamification::player.xp_unit')) } },
                    tooltip: { theme: 'dark' },
                    series: [{ name: @js(__('gamification::player.xp_unit')), data: @js($chartValues) }],
                }"
                wire:key="xp-daily-{{ count($chartValues) }}"
            ></div>
        </div>
    </x-ui.glass-card>

    <section class="mt-8">
        <h3 class="font-display text-lg font-semibold tracking-tight">{{ __('gamification::player.domains_title') }}</h3>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($domains as $domain)
                <x-ui.glass-card hover padding="p-5">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $domain->softBackgroundClass() }} {{ $domain->textClass() }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="{{ $domain->icon() }}" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $domain->label() }}</p>
                            <p class="font-display text-xl font-bold {{ $domain->textClass() }}">
                                {{ number_format($domainTotals[$domain->value] ?? 0, 0, ',', ' ') }} <span class="text-sm font-normal text-muted">{{ __('gamification::player.xp_unit') }}</span>
                            </p>
                        </div>
                    </div>
                </x-ui.glass-card>
            @endforeach
        </div>
    </section>
</div>
