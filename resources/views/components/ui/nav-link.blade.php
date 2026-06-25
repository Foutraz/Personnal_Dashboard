@props([
    'href' => '#',
    'active' => false,
    'disabled' => false,
    'icon' => null,
])

@php
    $base = 'group relative flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all duration-300';
    $state = match (true) {
        $disabled => 'cursor-not-allowed text-faint/70',
        $active => 'bg-cyan-soft text-ink',
        default => 'text-muted hover:bg-white/5 hover:text-ink',
    };
@endphp

@if ($disabled)
    <span {{ $attributes->class([$base, $state]) }} aria-disabled="true">
        @if ($active)
            <span class="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-cyan"></span>
        @endif
        <span class="flex h-5 w-5 items-center justify-center {{ $active ? 'text-cyan' : 'text-faint group-hover:text-ink' }}">{{ $icon }}</span>
        <span class="flex-1">{{ $slot }}</span>
        <span class="text-[0.6rem] uppercase tracking-wider text-faint">soon</span>
    </span>
@else
    <a href="{{ $href }}" @if ($active) wire:current @endif {{ $attributes->class([$base, $state]) }}>
        @if ($active)
            <span class="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-cyan"></span>
        @endif
        <span class="flex h-5 w-5 items-center justify-center {{ $active ? 'text-cyan' : 'text-faint group-hover:text-cyan' }}">{{ $icon }}</span>
        <span class="flex-1">{{ $slot }}</span>
    </a>
@endif
