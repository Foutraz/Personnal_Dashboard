@props(['item'])

@php
    $accents = [
        'cyan'   => ['text' => 'text-cyan',   'bg' => 'bg-cyan-soft',   'bar' => 'bg-cyan'],
        'violet' => ['text' => 'text-violet',  'bg' => 'bg-violet-soft', 'bar' => 'bg-violet'],
        'lime'   => ['text' => 'text-lime',    'bg' => 'bg-lime-soft',   'bar' => 'bg-lime'],
    ];
    $a = $accents[$item->accent] ?? $accents['cyan'];
@endphp

<a href="{{ $item->href ?? '#' }}" class="group glass glass-hover relative flex items-center gap-4 overflow-hidden p-4">
    <div class="absolute inset-y-0 left-0 w-0.5 {{ $a['bar'] }} opacity-40 transition-opacity duration-300 group-hover:opacity-100"></div>

    <div class="{{ $a['bg'] }} {{ $a['text'] }} flex min-w-[2.75rem] flex-col items-center rounded-xl px-2 py-2">
        <span class="font-display text-xl font-bold leading-none">{{ $item->startsAt->isoFormat('D') }}</span>
        <span class="mt-0.5 text-[0.6rem] font-semibold uppercase tracking-wider opacity-80">{{ $item->startsAt->isoFormat('MMM') }}</span>
    </div>

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ $item->title }}</p>
        <p class="mt-0.5 text-xs text-faint">
            {{ $item->allDay ? 'Toute la journée' : $item->startsAt->isoFormat('HH:mm') }}
            @if ($item->amount) · {{ $item->amount }} €@endif
            @if ($item->location) · {{ $item->location }}@endif
        </p>
    </div>

    <svg class="{{ $a['text'] }} h-4 w-4 shrink-0 opacity-0 transition-all duration-300 group-hover:translate-x-0.5 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6" />
    </svg>
</a>
