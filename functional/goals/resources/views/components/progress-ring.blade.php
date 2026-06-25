@props([
    'percentage' => 0,
    'stroke' => '#2ff3ff',
    'size' => 92,
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
        display: 0,
        init() {
            const arc = this.$refs.arc;
            const offset = this.circumference * (1 - this.target / 100);
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduce || !window.Motion) {
                arc.style.strokeDashoffset = offset;
                this.display = Math.round(this.target);
                return;
            }

            window.Motion.animate(
                (progress) => {
                    arc.style.strokeDashoffset = this.circumference * (1 - (this.target * progress) / 100);
                    this.display = Math.round(this.target * progress);
                },
                { duration: 1.1, easing: [0.22, 1, 0.36, 1] }
            );
        },
    }"
    x-init="init()"
    wire:ignore
>
    <svg class="h-full w-full -rotate-90" viewBox="0 0 92 92" aria-hidden="true">
        <circle cx="46" cy="46" r="{{ $radius }}" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="7" />
        <circle
            x-ref="arc"
            cx="46"
            cy="46"
            r="{{ $radius }}"
            fill="none"
            stroke="{{ $stroke }}"
            stroke-width="7"
            stroke-linecap="round"
            stroke-dasharray="{{ $circumference }}"
            stroke-dashoffset="{{ $circumference }}"
            style="filter: drop-shadow(0 0 6px {{ $stroke }}88);"
        />
    </svg>
    <div class="absolute inset-0 grid place-items-center">
        <span class="font-display text-lg font-bold" style="color: {{ $stroke }};"><span x-text="display">0</span>%</span>
    </div>
</div>
