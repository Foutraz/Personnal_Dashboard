@props([
    'title' => '',
    'iconPath' => null,
    'accent' => 'cyan',
    'href' => '#',
    'available' => false,
    'delay' => 0,
    'metricValue' => '',
    'metricUnit' => null,
    'secondaryLines' => [],
    'callToAction' => null,
])

@php
    $accents = [
        'cyan' => ['text' => 'text-cyan', 'bg' => 'bg-cyan-soft', 'ring' => 'group-hover:border-cyan/40', 'bar' => 'bg-cyan'],
        'violet' => ['text' => 'text-violet', 'bg' => 'bg-violet-soft', 'ring' => 'group-hover:border-violet/40', 'bar' => 'bg-violet'],
        'lime' => ['text' => 'text-lime', 'bg' => 'bg-lime-soft', 'ring' => 'group-hover:border-lime/40', 'bar' => 'bg-lime'],
    ];
    $a = $accents[$accent] ?? $accents['cyan'];
@endphp

<a
    href="{{ $href }}"
    x-data
    x-reveal:{{ $delay }}
    {{ $attributes->class(['group glass glass-hover relative flex flex-col overflow-hidden cursor-pointer']) }}
>
    <div class="absolute inset-y-0 left-0 w-0.5 {{ $a['bar'] }} opacity-40 transition-opacity duration-300 group-hover:opacity-100"></div>

    <div class="flex flex-col p-6">
        <div class="flex items-start justify-between">
            <span class="grid h-12 w-12 place-items-center rounded-2xl {{ $a['bg'] }} {{ $a['text'] }}">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}"/></svg>
            </span>

            @if ($available)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                    <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Actif
                </span>
            @else
                <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">
                    À connecter
                </span>
            @endif
        </div>

        <h3 class="mt-5 font-display text-lg font-semibold tracking-tight">{{ $title }}</h3>

        @if ($available)
            <div class="mt-3 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold {{ $a['text'] }}">{{ $metricValue }}</span>
                @if ($metricUnit)
                    <span class="text-sm text-muted">{{ $metricUnit }}</span>
                @endif
            </div>
            <div class="mt-2 flex flex-col gap-0.5">
                @foreach ($secondaryLines as $line)
                    <p class="text-xs text-muted">{{ $line }}</p>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ $callToAction }}</p>
        @endif

        <div class="mt-5 flex items-center gap-1.5 text-sm font-medium {{ $a['text'] }}">
            <span>Ouvrir</span>
            <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
        </div>
    </div>

    <div class="pointer-events-none absolute -bottom-12 -right-12 h-32 w-32 rounded-full {{ $a['bg'] }} opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"></div>
</a>
