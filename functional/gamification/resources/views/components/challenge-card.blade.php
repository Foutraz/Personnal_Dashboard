@props(['card'])

<x-ui.glass-card hover padding="p-5" {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $card->domain->softBackgroundClass() }} {{ $card->domain->textClass() }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="{{ $card->domain->icon() }}" />
                </svg>
            </span>
            <div>
                <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $card->domain->label() }}</p>
                <p class="font-display text-base font-semibold">{{ $card->name() }}</p>
            </div>
        </div>
        <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wider {{ $card->chipClass() }}">{{ $card->statusLabel() }}</span>
    </div>

    <div>
        <p class="text-sm text-ink">{{ $card->description() }}</p>
        <p class="mt-1 text-xs text-faint">{{ $card->baselineLabel() }}</p>
    </div>

    @if ($card->tracksProgress())
        <div>
            <div
                role="progressbar"
                aria-label="{{ $card->progressAccessibleLabel() }}"
                aria-valuenow="{{ $card->roundedPercentage() }}"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuetext="{{ $card->progressLabel() }}"
                class="h-1.5 w-full overflow-hidden rounded-full bg-surface-2 {{ $card->domain->textClass() }}"
            >
                <div
                    class="h-full rounded-full bg-current"
                    style="width: {{ $card->barWidth() }}; box-shadow: 0 0 10px currentColor;"
                ></div>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                <span class="text-muted">{{ $card->progressLabel() }}</span>
                <span class="font-medium {{ $card->domain->textClass() }}">{{ $card->rewardLabel() }}</span>
            </div>

            @unless ($card->isClosed())
                <p class="mt-1 text-xs text-faint">{{ $card->updatedLabel() }}</p>
            @endunless
        </div>
    @endif

    @if ($card->isRespondable)
        @if ($card->isAlreadyReached())
            <p class="text-xs font-medium {{ $card->domain->textClass() }}">{{ __('gamification::challenges.board.already_reached') }}</p>
        @endif

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                wire:click="accept('{{ $card->id }}')"
                wire:loading.attr="disabled"
                aria-label="{{ $card->acceptAccessibleLabel() }}"
                class="inline-flex items-center gap-2 rounded-xl border border-cyan/30 bg-cyan-soft px-4 py-2 text-sm font-medium text-cyan transition hover:border-cyan/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan/50 disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 13l4 4L19 7" />
                </svg>
                {{ __('gamification::challenges.board.accept') }}
            </button>

            <button
                type="button"
                wire:click="decline('{{ $card->id }}')"
                wire:confirm="{{ __('gamification::challenges.board.decline_confirm') }}"
                wire:loading.attr="disabled"
                aria-label="{{ $card->declineAccessibleLabel() }}"
                class="inline-flex items-center gap-2 rounded-xl border border-hairline px-4 py-2 text-sm font-medium text-muted transition hover:border-white/20 hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan/50 disabled:opacity-50"
            >
                {{ __('gamification::challenges.board.decline') }}
            </button>
        </div>
    @endif
</x-ui.glass-card>
