<div>
    <x-slot:header>Objectifs</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Module Objectifs &amp; Suivi</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Vos ambitions, <span class="text-cyan">mesurées</span> en continu.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Fixez des objectifs sportifs, financiers ou personnels. La progression est calculée automatiquement
                à partir de vos modules Sport et Finance, ou suivie manuellement.
            </p>
        </div>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3" style="animation-delay: 0.05s;">
        <x-ui.stat-tile
            label="Objectifs actifs"
            :value="$activeCount"
            accent="cyan"
            trend="Suivi en temps réel"
        />
        <x-ui.stat-tile
            label="Objectifs atteints"
            :value="$achievedCount"
            accent="lime"
            trend="Cibles franchies"
        />
        <x-ui.glass-card hover padding="p-5">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Progression moyenne</p>
            <div class="mt-4 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold text-violet">{{ (int) $averageProgress }}</span>
                <span class="text-sm text-muted">%</span>
            </div>
            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
                <div
                    class="h-full rounded-full bg-violet"
                    style="width: {{ min((int) $averageProgress, 100) }}%; box-shadow: 0 0 12px rgba(157,107,255,0.7);"
                ></div>
            </div>
        </x-ui.glass-card>
    </section>

    <x-ui.glass-card class="mt-8">
        <h3 class="font-display text-lg font-semibold tracking-tight">Nouvel objectif</h3>
        <p class="mt-0.5 text-sm text-muted">Choisissez une métrique : le suivi sportif et financier se calcule tout seul.</p>

        <form wire:submit="createGoal" class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <input
                    type="text"
                    wire:model="newTitle"
                    placeholder="Titre de l'objectif"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none"
                />
                @error('newTitle')<p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="relative lg:col-span-3">
                <select
                    wire:model="newMetric"
                    class="w-full appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
                >
                    @foreach ($metrics as $metricOption)
                        <option value="{{ $metricOption->value }}">{{ $metricOption->type()->label() }} · {{ $metricOption->label() }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </div>

            <div class="lg:col-span-2">
                <input
                    type="number"
                    step="any"
                    wire:model="newTargetValue"
                    placeholder="Cible"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none"
                />
                @error('newTargetValue')<p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="lg:col-span-2">
                <input
                    type="text"
                    wire:model="newUnit"
                    placeholder="Unité"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none"
                />
            </div>

            <div class="lg:col-span-1">
                <x-ui.neon-button type="submit" variant="cyan" class="w-full">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                </x-ui.neon-button>
            </div>

            <div class="lg:col-span-6">
                <input
                    type="text"
                    wire:model="newDescription"
                    placeholder="Description (optionnel)"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none"
                />
            </div>
            <div class="lg:col-span-6">
                <input
                    type="date"
                    wire:model="newDeadline"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-muted transition focus:border-cyan/40 focus:outline-none"
                />
            </div>
        </form>
    </x-ui.glass-card>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <div class="relative">
            <select
                wire:model.live="typeFilter"
                class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
            >
                <option value="all">Tous les types</option>
                @foreach ($types as $typeOption)
                    <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
        </div>
        <div class="relative">
            <select
                wire:model.live="statusFilter"
                class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
            >
                <option value="all">Tous les statuts</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($goals as $goal)
            @php
                $item = $progress[$goal->id];
                $accent = $goal->type->color();
                $accentText = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'][$accent] ?? 'text-cyan';
                $accentStroke = ['cyan' => '#2ff3ff', 'violet' => '#9d6bff', 'lime' => '#c5ff4a'][$accent] ?? '#2ff3ff';
                $clamped = $item->clampedPercentage();
            @endphp
            <x-ui.glass-card
                wire:key="goal-{{ $goal->id }}"
                hover
                padding="p-5"
                x-data="{}"
                x-init="$el.animate([{ opacity: 0, transform: 'translateY(12px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 420, easing: 'cubic-bezier(0.22,1,0.36,1)' })"
                class="group flex flex-col"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="rounded-full border border-hairline px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wider {{ $accentText }}">{{ $goal->type->label() }}</span>
                        <h4 class="mt-2 truncate font-display text-base font-semibold tracking-tight">{{ $goal->title }}</h4>
                        <p class="mt-0.5 text-xs text-faint">{{ $goal->metric->label() }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="deleteGoal('{{ $goal->id }}')"
                        wire:confirm="Supprimer cet objectif ?"
                        class="text-faint opacity-0 transition hover:text-rose-400 group-hover:opacity-100"
                        aria-label="Supprimer l'objectif"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg>
                    </button>
                </div>

                <div class="mt-4 flex items-center gap-5">
                    <x-goals::progress-ring
                        :percentage="$clamped"
                        :stroke="$accentStroke"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-[0.65rem] uppercase tracking-[0.2em] text-faint">Progression</p>
                        <p class="mt-1 font-display text-2xl font-bold {{ $accentText }}">
                            {{ rtrim(rtrim(number_format($item->currentValue, 2, ',', ' '), '0'), ',') }}
                            <span class="text-sm text-muted">/ {{ rtrim(rtrim(number_format($item->targetValue, 2, ',', ' '), '0'), ',') }} {{ $goal->unit }}</span>
                        </p>
                        @if ($goal->status === \Functional\Goals\Enums\GoalStatus::Achieved)
                            <p class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-lime">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                                Objectif atteint
                            </p>
                        @elseif ($goal->status === \Functional\Goals\Enums\GoalStatus::Archived)
                            <p class="mt-2 text-xs text-faint">Archivé</p>
                        @else
                            <p class="mt-2 text-xs {{ $item->onTrack ? 'text-lime' : 'text-amber-400' }}">
                                {{ $item->onTrack ? 'Dans les temps' : 'En retard sur le rythme' }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($goal->deadline)
                    <p class="mt-4 text-xs text-faint">Échéance : {{ $goal->deadline->translatedFormat('d M Y') }}</p>
                @endif

                <div class="mt-4 flex items-center gap-2">
                    @if ($goal->metric === \Functional\Goals\Enums\GoalMetric::Manual && $goal->status !== \Functional\Goals\Enums\GoalStatus::Archived)
                        <button
                            type="button"
                            wire:click="startEditing('{{ $goal->id }}')"
                            class="flex-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-muted transition hover:border-violet/40 hover:text-violet"
                        >
                            Mettre à jour
                        </button>
                    @endif
                    @if ($goal->status === \Functional\Goals\Enums\GoalStatus::Archived)
                        <button
                            type="button"
                            wire:click="reactivateGoal('{{ $goal->id }}')"
                            class="flex-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-muted transition hover:border-cyan/40 hover:text-cyan"
                        >
                            Réactiver
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="archiveGoal('{{ $goal->id }}')"
                            class="flex-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-muted transition hover:border-violet/40 hover:text-violet"
                        >
                            Archiver
                        </button>
                    @endif
                </div>

                @if ($editingGoalId === $goal->id)
                    <form wire:submit="updateManualValue" class="mt-3 flex items-center gap-2">
                        <input
                            type="number"
                            step="any"
                            wire:model="editManualValue"
                            placeholder="Valeur actuelle"
                            class="flex-1 rounded-lg border border-hairline bg-surface px-3 py-1.5 text-sm text-ink transition focus:border-violet/40 focus:outline-none"
                        />
                        <x-ui.neon-button type="submit" variant="violet" class="!px-3 !py-1.5 !text-xs">OK</x-ui.neon-button>
                    </form>
                    @error('editManualValue')<p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>@enderror
                @endif
            </x-ui.glass-card>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-ui.glass-card>
                    <p class="py-12 text-center text-sm text-faint">Aucun objectif. Créez votre premier objectif pour démarrer le suivi automatique.</p>
                </x-ui.glass-card>
            </div>
        @endforelse
    </div>
</div>
