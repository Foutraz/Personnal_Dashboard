@props([
    'title' => '',
    'description' => '',
    'icon' => null,
    'accent' => 'cyan',
    'href' => null,
    'available' => false,
    'delay' => 0,
])

@php
    $accents = [
        'cyan' => ['text' => 'text-cyan', 'bg' => 'bg-cyan-soft', 'ring' => 'group-hover:border-cyan/40'],
        'violet' => ['text' => 'text-violet', 'bg' => 'bg-violet-soft', 'ring' => 'group-hover:border-violet/40'],
        'lime' => ['text' => 'text-lime', 'bg' => 'bg-lime-soft', 'ring' => 'group-hover:border-lime/40'],
    ];
    $a = $accents[$accent] ?? $accents['cyan'];
    $tag = ($available && $href) ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($available && $href) href="{{ $href }}" @endif
    x-data
    x-reveal:{{ $delay }}
    {{ $attributes->class([
        'group glass relative flex flex-col overflow-hidden p-6',
        'glass-hover cursor-pointer' => $available,
        'cursor-default opacity-70' => ! $available,
    ]) }}
>
    <div class="flex items-start justify-between">
        <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $a['bg'] }} {{ $a['text'] }}">
            {{ $icon }}
        </span>

        @if ($available)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Actif
            </span>
        @else
            <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">
                Bientôt
            </span>
        @endif
    </div>

    <h3 class="mt-5 font-display text-lg font-semibold tracking-tight">{{ $title }}</h3>
    <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ $description }}</p>

    <div class="mt-5 flex items-center gap-1.5 text-sm font-medium {{ $available ? $a['text'] : 'text-faint' }}">
        @if ($available)
            <span>Ouvrir</span>
            <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
        @else
            <span>En préparation</span>
        @endif
    </div>

    <div class="pointer-events-none absolute -bottom-12 -right-12 h-32 w-32 rounded-full {{ $a['bg'] }} opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"></div>
</{{ $tag }}>
