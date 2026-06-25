@props([
    'type' => 'button',
    'href' => null,
    'variant' => 'cyan',
    'disabled' => false,
])

@php
    $variants = [
        'cyan' => 'text-cyan border-cyan/40 hover:bg-cyan-soft hover:shadow-[0_0_28px_-6px_rgba(47,243,255,0.65)]',
        'violet' => 'text-violet border-violet/40 hover:bg-violet-soft hover:shadow-[0_0_28px_-6px_rgba(157,107,255,0.65)]',
        'lime' => 'text-lime border-lime/40 hover:bg-lime-soft hover:shadow-[0_0_28px_-6px_rgba(197,255,74,0.6)]',
        'ghost' => 'text-muted border-hairline hover:text-ink hover:border-white/20',
    ];
    $base = 'group relative inline-flex items-center justify-center gap-2 rounded-xl border px-5 py-2.5 text-sm font-medium tracking-wide transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan/50';
    $state = $disabled ? 'pointer-events-none opacity-40' : '';
    $classes = trim($base.' '.($variants[$variant] ?? $variants['cyan']).' '.$state);
@endphp

@if ($href && ! $disabled)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>
        {{ $slot }}
    </button>
@endif
