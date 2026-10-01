@props(['family'])

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
            <span class="text-muted">{{ __('gamification::badges.showcase.current', ['measure' => $family->currentLabel()]) }}</span>
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
