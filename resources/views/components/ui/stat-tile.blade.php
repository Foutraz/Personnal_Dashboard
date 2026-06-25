@props([
    'label' => '',
    'value' => '',
    'unit' => null,
    'accent' => 'cyan',
    'trend' => null,
])

@php
    $accents = [
        'cyan' => 'text-cyan',
        'violet' => 'text-violet',
        'lime' => 'text-lime',
    ];
    $accentClass = $accents[$accent] ?? $accents['cyan'];
@endphp

<x-ui.glass-card hover padding="p-5">
    <div class="flex items-start justify-between">
        <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">{{ $label }}</p>
        @if ($icon ?? false)
            <span class="{{ $accentClass }}">{{ $icon }}</span>
        @endif
    </div>
    <div class="mt-4 flex items-baseline gap-1.5">
        <span class="font-display text-3xl font-bold {{ $accentClass }}">{{ $value }}</span>
        @if ($unit)
            <span class="text-sm text-muted">{{ $unit }}</span>
        @endif
    </div>
    @if ($trend)
        <p class="mt-2 text-xs text-muted">{{ $trend }}</p>
    @endif
</x-ui.glass-card>
