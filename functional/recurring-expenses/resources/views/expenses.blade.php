<div>
    <x-slot:header>Échéances</x-slot:header>

    @php
        $accentText = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'];
        $accentDot = ['cyan' => 'bg-cyan', 'violet' => 'bg-violet', 'lime' => 'bg-lime'];
        $accentBadge = [
            'cyan' => 'border-cyan/40 bg-cyan-soft text-cyan',
            'violet' => 'border-violet/40 bg-violet-soft text-violet',
            'lime' => 'border-lime/40 bg-lime-soft text-lime',
        ];
    @endphp

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Module Échéances</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Vos <span class="text-cyan">charges récurrentes</span>, anticipées.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Loyer, assurances, abonnements et crédits réunis dans un calendrier néon. Recevez des rappels automatiques avant chaque échéance.
            </p>
        </div>
    </section>

    <section class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" style="animation-delay: 0.05s;">
        <x-ui.stat-tile
            label="Total mensuel"
            :value="number_format($monthlyTotal, 2, ',', ' ')"
            unit="€/mois"
            accent="cyan"
            trend="Coût mensualisé toutes fréquences"
        />
        <x-ui.stat-tile
            label="Total annuel"
            :value="number_format($yearlyTotal, 2, ',', ' ')"
            unit="€/an"
            accent="violet"
            trend="Projection sur douze mois"
        />
        <x-ui.stat-tile
            label="Échéances actives"
            :value="(string) $activeCount"
            accent="lime"
            trend="Charges en cours de suivi"
        />
        <x-ui.stat-tile
            label="À venir (30 j)"
            :value="(string) $upcoming->count()"
            accent="cyan"
            trend="Prochaines échéances proches"
        />
    </section>

    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-ui.glass-card>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-display text-lg font-semibold tracking-tight">Calendrier des échéances</h3>
                        <p class="mt-0.5 text-sm capitalize text-muted">{{ $calendar['label'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousMonth" class="grid h-9 w-9 place-items-center rounded-xl border border-hairline text-faint transition hover:border-cyan/40 hover:text-cyan" aria-label="Mois précédent">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <button type="button" wire:click="nextMonth" class="grid h-9 w-9 place-items-center rounded-xl border border-hairline text-faint transition hover:border-cyan/40 hover:text-cyan" aria-label="Mois suivant">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-7 gap-1.5 text-center text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-faint">
                    @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>

                <div class="mt-2 flex flex-col gap-1.5">
                    @foreach ($calendar['weeks'] as $week)
                        <div class="grid grid-cols-7 gap-1.5">
                            @foreach ($week as $cell)
                                @php $hasDue = count($cell['expenses']) > 0; @endphp
                                <div @class([
                                    'relative min-h-[4.5rem] rounded-xl border p-1.5 text-left transition',
                                    'border-hairline bg-surface/40' => ! $hasDue && $cell['day'] !== null,
                                    'border-transparent' => $cell['day'] === null,
                                    'border-cyan/40 bg-cyan-soft' => $hasDue,
                                ])>
                                    @if ($cell['day'] !== null)
                                        <span @class(['text-xs font-medium', 'text-cyan' => $hasDue, 'text-faint' => ! $hasDue])>{{ $cell['day'] }}</span>
                                        <div class="mt-1 flex flex-col gap-0.5">
                                            @foreach (array_slice($cell['expenses'], 0, 2) as $expense)
                                                <div
                                                    x-data
                                                    x-init="$el.animate([{ opacity: 0 }, { opacity: 1 }], { duration: 280, easing: 'ease-out' })"
                                                    class="flex items-center gap-1 truncate rounded-md px-1 py-0.5 text-[0.6rem] {{ $accentBadge[$expense->category->color()] ?? $accentBadge['cyan'] }}"
                                                    title="{{ $expense->label }} — {{ number_format((float) $expense->amount, 2, ',', ' ') }} €"
                                                >
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $accentDot[$expense->category->color()] ?? $accentDot['cyan'] }}"></span>
                                                    <span class="truncate">{{ $expense->label }}</span>
                                                </div>
                                            @endforeach
                                            @if (count($cell['expenses']) > 2)
                                                <span class="px-1 text-[0.6rem] text-faint">+{{ count($cell['expenses']) - 2 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-ui.glass-card>
        </div>

        <div class="flex flex-col gap-6">
            <x-ui.glass-card>
                <h3 class="font-display text-lg font-semibold tracking-tight">Répartition par catégorie</h3>
                <p class="mt-0.5 text-sm text-muted">Coût mensualisé par catégorie.</p>

                @if ($monthlyTotal <= 0)
                    <p class="mt-6 text-center text-sm text-faint">Aucune échéance active à analyser.</p>
                @else
                    <div wire:loading.delay class="mt-6 h-56 animate-pulse rounded-2xl bg-white/5"></div>
                    <div wire:loading.remove class="mt-4" wire:ignore>
                        <div
                            x-data
                            x-apexchart="{
                                chart: { type: 'donut', height: 224 },
                                labels: @js($categoryLabels),
                                series: @js($categoryValues),
                                legend: { position: 'bottom', labels: { colors: '#9a9bb4' } },
                                plotOptions: { pie: { donut: { size: '70%' } } },
                                dataLabels: { enabled: false },
                                stroke: { width: 0 },
                                tooltip: { y: { formatter: (v) => v.toFixed(2) + ' €' } },
                            }"
                            wire:key="category-donut-{{ $monthlyTotal }}-{{ implode('-', $categoryValues) }}"
                        ></div>
                    </div>
                @endif
            </x-ui.glass-card>

            <x-ui.glass-card>
                <h3 class="font-display text-lg font-semibold tracking-tight">Prochaines échéances</h3>
                <p class="mt-0.5 text-sm text-muted">Sur les 30 prochains jours.</p>

                <div class="mt-4 flex flex-col gap-2.5">
                    @forelse ($upcoming as $expense)
                        <div wire:key="upcoming-{{ $expense->id }}" class="flex items-center gap-3 rounded-xl border border-hairline bg-surface/50 px-3 py-2.5">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $accentDot[$expense->category->color()] ?? $accentDot['cyan'] }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $expense->label }}</p>
                                <p class="text-xs text-faint">{{ $expense->next_due_at->translatedFormat('d M') }} · {{ $expense->category->label() }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold {{ $accentText[$expense->category->color()] ?? $accentText['cyan'] }}">
                                {{ number_format((float) $expense->amount, 2, ',', ' ') }} €
                            </span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-faint">Aucune échéance dans les 30 jours.</p>
                    @endforelse
                </div>
            </x-ui.glass-card>
        </div>
    </section>

    <section class="mt-8">
        <x-ui.glass-card>
            <form wire:submit="createExpense" class="grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-start">
                <div class="lg:col-span-3">
                    <input type="text" wire:model="newLabel" placeholder="Libellé (ex. Loyer)" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none" />
                    @error('newLabel') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-2">
                    <input type="number" step="0.01" min="0" wire:model="newAmount" placeholder="Montant €" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-cyan/40 focus:outline-none" />
                    @error('newAmount') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="relative lg:col-span-2">
                    <select wire:model="newCategory" class="w-full appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none">
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="relative lg:col-span-2">
                    <select wire:model="newFrequency" class="w-full appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none">
                        @foreach ($frequencies as $frequency)
                            <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </div>
                <div class="lg:col-span-2">
                    <input type="date" wire:model="newNextDueAt" class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition focus:border-cyan/40 focus:outline-none" />
                    @error('newNextDueAt') <p class="mt-1.5 text-xs text-violet">{{ $message }}</p> @enderror
                </div>
                <div class="lg:col-span-1">
                    <x-ui.neon-button type="submit" variant="cyan" class="w-full">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    </x-ui.neon-button>
                </div>
            </form>
        </x-ui.glass-card>
    </section>

    <section class="mt-6">
        <x-ui.glass-card>
            <h3 class="font-display text-lg font-semibold tracking-tight">Toutes les échéances</h3>
            <p class="mt-0.5 text-sm text-muted">Activez, désactivez ou supprimez vos charges.</p>

            <div wire:loading.delay.class="opacity-40" class="mt-5 flex flex-col gap-2.5 transition-opacity">
                @forelse ($expenses as $expense)
                    @php $badge = $accentBadge[$expense->category->color()] ?? $accentBadge['cyan']; @endphp
                    <div
                        wire:key="expense-{{ $expense->id }}"
                        x-data
                        x-init="$el.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 320, easing: 'cubic-bezier(0.22,1,0.36,1)' })"
                        @class([
                            'group flex items-center gap-3.5 rounded-2xl border border-hairline bg-surface/60 px-4 py-3.5 transition-all duration-300 hover:border-white/15',
                            'opacity-50' => ! $expense->active,
                        ])
                    >
                        <button
                            type="button"
                            wire:click="toggleActive('{{ $expense->id }}')"
                            @class([
                                'grid h-6 w-6 shrink-0 place-items-center rounded-full border transition-all duration-300',
                                'border-lime bg-lime-soft text-lime' => $expense->active,
                                'border-hairline text-transparent hover:border-lime/50' => ! $expense->active,
                            ])
                            aria-label="Basculer l'activation"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </button>

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $expense->label }}</p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-faint">
                                <span>{{ $expense->frequency->label() }}</span>
                                <span aria-hidden="true">•</span>
                                <span>Prochaine : {{ $expense->next_due_at->translatedFormat('d/m/Y') }}</span>
                            </div>
                        </div>

                        <span class="shrink-0 rounded-full border px-3 py-1 text-[0.7rem] font-medium {{ $badge }}">{{ $expense->category->label() }}</span>

                        <span class="shrink-0 text-sm font-semibold {{ $accentText[$expense->category->color()] ?? $accentText['cyan'] }}">
                            {{ number_format((float) $expense->amount, 2, ',', ' ') }} {{ $expense->currency }}
                        </span>

                        <button
                            type="button"
                            wire:click="deleteExpense('{{ $expense->id }}')"
                            wire:confirm="Supprimer cette échéance ?"
                            class="shrink-0 text-faint opacity-0 transition hover:text-violet group-hover:opacity-100"
                            aria-label="Supprimer l'échéance"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg>
                        </button>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-faint">Aucune échéance. Ajoutez-en une pour démarrer.</p>
                @endforelse
            </div>
        </x-ui.glass-card>
    </section>
</div>
