<section class="mt-8" style="animation-delay: 0.12s;" aria-labelledby="challenge-board-title">
    <h3 id="challenge-board-title" class="font-display text-lg font-semibold tracking-tight">{{ __('gamification::challenges.board.title') }}</h3>
    <p class="mt-0.5 text-sm text-muted">{{ $subtitle }}</p>

    <div role="status" aria-live="polite" class="sr-only">{{ $announcement }}</div>

    @if ($currentCards->isEmpty())
        <x-ui.glass-card padding="p-5" class="mt-4">
            <p class="text-sm text-muted">{{ __('gamification::challenges.board.empty', ['weeks' => $historyWeeks]) }}</p>
        </x-ui.glass-card>
    @else
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($currentCards as $card)
                <x-gamification::challenge-card :card="$card" wire:key="challenge-{{ $card->id }}" />
            @endforeach
        </div>
    @endif

    @if ($previousCards->isNotEmpty())
        <div class="mt-6">
            <h4 class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ __('gamification::challenges.board.previous_title') }}</h4>

            <ul class="mt-3 flex flex-col gap-2">
                @foreach ($previousCards as $card)
                    <li wire:key="previous-challenge-{{ $card->id }}">
                        <x-ui.glass-card padding="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg {{ $card->domain->softBackgroundClass() }} {{ $card->domain->textClass() }}">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="{{ $card->domain->icon() }}" />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold">{{ $card->name() }}</p>
                                        @if ($card->tracksProgress())
                                            <p class="text-xs text-muted">{{ $card->progressLabel() }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    @if ($card->isAwaitingClosure())
                                        <span class="text-xs text-faint">{{ __('gamification::challenges.board.grace_pending') }}</span>
                                    @else
                                        @if ($card->hasEarnedReward())
                                            <span class="text-xs font-medium {{ $card->domain->textClass() }}">{{ $card->rewardLabel() }}</span>
                                        @endif
                                        <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wider {{ $card->chipClass() }}">{{ $card->statusLabel() }}</span>
                                    @endif
                                </div>
                            </div>
                        </x-ui.glass-card>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</section>
