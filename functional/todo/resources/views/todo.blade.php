<div>
    <x-slot:header>To-Do</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-lime">Module To-Do</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Vos <span class="text-lime">tâches</span> sous contrôle.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Créez, priorisez et complétez vos tâches en un geste. Le board agrège vos priorités et vous rappelle vos échéances.
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        @livewire('todo-statistics')
    </section>

    <section class="mt-8">
        <x-ui.glass-card>
            <form wire:submit="createTask" class="flex flex-col gap-3 lg:flex-row lg:items-start">
                <div class="flex-1">
                    <input
                        type="text"
                        wire:model="newTitle"
                        placeholder="Nouvelle tâche…"
                        class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-lime/40 focus:outline-none"
                    />
                    @error('newTitle')
                        <p class="mt-1.5 text-xs text-violet">{{ $message }}</p>
                    @enderror
                </div>

                <div class="relative">
                    <select
                        wire:model="newPriority"
                        class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink transition focus:border-lime/40 focus:outline-none"
                    >
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                </div>

                <input
                    type="datetime-local"
                    wire:model="newDueAt"
                    class="rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition focus:border-lime/40 focus:outline-none"
                />

                <x-ui.neon-button type="submit" variant="lime">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    Ajouter
                </x-ui.neon-button>
            </form>
        </x-ui.glass-card>
    </section>

    <section class="mt-6">
        <x-ui.glass-card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-display text-lg font-semibold tracking-tight">Tâches</h3>
                    <p class="mt-0.5 text-sm text-muted">Cliquez pour compléter, faites défiler les priorités.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative">
                        <select
                            wire:model.live="statusFilter"
                            class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
                        >
                            <option value="">Tous les statuts</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </div>

                    <div class="relative">
                        <select
                            wire:model.live="priorityFilter"
                            class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
                        >
                            <option value="">Toutes priorités</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                    </div>
                </div>
            </div>

            <div wire:loading.delay.class="opacity-40" class="mt-5 flex flex-col gap-2.5 transition-opacity">
                @forelse ($tasks as $task)
                    @php
                        $priorityBadges = [
                            'cyan' => 'border-cyan/40 bg-cyan-soft text-cyan hover:border-cyan/70',
                            'violet' => 'border-violet/40 bg-violet-soft text-violet hover:border-violet/70',
                            'lime' => 'border-lime/40 bg-lime-soft text-lime hover:border-lime/70',
                        ];
                        $priorityBadge = $priorityBadges[$task->priority->color()] ?? $priorityBadges['cyan'];
                        $isDone = $task->status === \Functional\Todo\Enums\TaskStatus::Done;
                    @endphp
                    <div
                        wire:key="task-{{ $task->id }}"
                        x-data
                        x-init="$el.animate([{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 320, easing: 'cubic-bezier(0.22,1,0.36,1)' })"
                        @class([
                            'group flex items-center gap-3.5 rounded-2xl border border-hairline bg-surface/60 px-4 py-3.5 transition-all duration-300 hover:border-white/15',
                            'opacity-60' => $isDone,
                        ])
                    >
                        <button
                            type="button"
                            wire:click="toggleComplete('{{ $task->id }}')"
                            @class([
                                'grid h-6 w-6 shrink-0 place-items-center rounded-full border transition-all duration-300',
                                'border-lime bg-lime-soft text-lime' => $isDone,
                                'border-hairline text-transparent hover:border-lime/50' => ! $isDone,
                            ])
                            aria-label="Basculer la complétion"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                        </button>

                        <div class="min-w-0 flex-1">
                            <p @class(['truncate text-sm font-medium', 'line-through text-faint' => $isDone])>{{ $task->title }}</p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-faint">
                                <span>{{ $task->status->label() }}</span>
                                @if ($task->due_at)
                                    <span aria-hidden="true">•</span>
                                    <span>{{ $task->due_at->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                        </div>

                        <button
                            type="button"
                            wire:click="cyclePriority('{{ $task->id }}')"
                            class="shrink-0 rounded-full border px-3 py-1 text-[0.7rem] font-medium transition {{ $priorityBadge }}"
                            title="Changer la priorité"
                        >
                            {{ $task->priority->label() }}
                        </button>

                        <button
                            type="button"
                            wire:click="deleteTask('{{ $task->id }}')"
                            wire:confirm="Supprimer cette tâche ?"
                            class="shrink-0 text-faint opacity-0 transition hover:text-violet group-hover:opacity-100"
                            aria-label="Supprimer la tâche"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg>
                        </button>
                    </div>
                @empty
                    <p class="py-12 text-center text-sm text-faint">Aucune tâche. Ajoutez-en une pour démarrer.</p>
                @endforelse
            </div>
        </x-ui.glass-card>
    </section>
</div>
