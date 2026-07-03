@props([
    'level' => 1,
    'percentage' => 0,
    'stroke' => '#9d6bff',
    'size' => 148,
])

@php
    $radius = 40;
    $circumference = 2 * M_PI * $radius;
    $target = max(min((float) $percentage, 100), 0);
@endphp

<div
    class="relative shrink-0"
    style="width: {{ $size }}px; height: {{ $size }}px;"
    x-data="{
        target: {{ $target }},
        circumference: {{ $circumference }},
        init() {
            const arc = this.$refs.arc;
            const offset = this.circumference * (1 - this.target / 100);
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduce || !window.Motion) {
                arc.style.strokeDashoffset = offset;
                return;
            }

            window.Motion.animate(
                (progress) => {
                    arc.style.strokeDashoffset = this.circumference * (1 - (this.target * progress) / 100);
                },
                { duration: 1.2, easing: [0.22, 1, 0.36, 1] }
            );
        },
    }"
    x-init="init()"
    wire:ignore
>
    <svg class="h-full w-full -rotate-90" viewBox="0 0 92 92" aria-hidden="true">
        <circle cx="46" cy="46" r="{{ $radius }}" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="6" />
        <circle
            x-ref="arc"
            cx="46"
            cy="46"
            r="{{ $radius }}"
            fill="none"
            stroke="{{ $stroke }}"
            stroke-width="6"
            stroke-linecap="round"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $circumference }}"
            style="filter: drop-shadow(0 0 8px {{ $stroke }}88);"
        />
    </svg>
    <div class="absolute inset-0 grid place-items-center">
        <div class="text-center">
            <p class="text-[0.6rem] font-medium uppercase tracking-[0.25em] text-faint">Niveau</p>
            <p class="font-display text-4xl font-bold leading-none" style="color: {{ $stroke }};">{{ $level }}</p>
        </div>
    </div>
</div>
