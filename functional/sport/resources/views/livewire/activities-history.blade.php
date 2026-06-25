<div>
    <x-ui.glass-card>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-display text-lg font-semibold tracking-tight">Historique</h3>
                <p class="mt-0.5 text-sm text-muted">Vos dernières activités synchronisées.</p>
            </div>

            <div class="relative">
                <select
                    wire:model.live="sportType"
                    class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2 pr-9 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
                >
                    <option value="">Toutes les disciplines</option>
                    @foreach ($sportTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </div>
        </div>

        <div wire:loading.delay.class="opacity-40" class="mt-5 transition-opacity">
            @forelse ($activities as $activity)
                <div class="flex items-center justify-between gap-4 border-b border-hairline py-3.5 last:border-0">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-cyan-soft text-cyan">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7Z"/></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $activity->name }}</p>
                            <p class="text-xs text-faint">{{ $activity->sport_type->label() }} • {{ $activity->started_at->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-display text-sm font-semibold text-cyan">{{ number_format($activity->distance / 1000, 1, ',', ' ') }} km</p>
                        <p class="text-xs text-faint">{{ floor($activity->moving_time / 60) }} min</p>
                    </div>
                </div>
            @empty
                <p class="py-10 text-center text-sm text-faint">Aucune activité pour ce filtre.</p>
            @endforelse
        </div>

        @if ($activities->hasPages())
            <div class="mt-5">
                {{ $activities->links() }}
            </div>
        @endif
    </x-ui.glass-card>
</div>
