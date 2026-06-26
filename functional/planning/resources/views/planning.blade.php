<div>
    <x-slot:header>Planning</x-slot:header>

    @php
        $sourceDot = ['cyan' => 'bg-cyan', 'violet' => 'bg-violet', 'lime' => 'bg-lime'];
        $sourceChip = [
            'cyan' => 'border-cyan/40 bg-cyan-soft text-cyan',
            'violet' => 'border-violet/40 bg-violet-soft text-violet',
            'lime' => 'border-lime/40 bg-lime-soft text-lime',
        ];
        $sourceText = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'];
    @endphp

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-violet">Module Planning</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Tous vos agendas, <span class="text-violet">une seule</span> orbite.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Agrégez Outlook et Google Calendar, superposez vos échéances financières et naviguez d'une vue mois à une vue semaine dans un calendrier néon.
            </p>
        </div>
    </section>

    {{-- Connect cards --}}
    <section class="mt-8 grid grid-cols-1 gap-4 lg:grid-cols-3">
        @php
            $providers = [
                ['key' => 'google', 'name' => 'Google Calendar', 'accent' => 'cyan', 'connection' => $google, 'lastSync' => $googleLastSync],
                ['key' => 'outlook', 'name' => 'Outlook', 'accent' => 'violet', 'connection' => $outlook, 'lastSync' => $outlookLastSync],
            ];
        @endphp

        @foreach ($providers as $provider)
            @php $connected = $provider['connection'] !== null; @endphp
            <x-ui.glass-card :hover="true" wire:key="provider-{{ $provider['key'] }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl {{ $sourceChip[$provider['accent']] }} border">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3m10-3v3M4 9h16M5 6h14a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1Z"/></svg>
                        </span>
                        <div>
                            <h3 class="font-display text-base font-semibold tracking-tight">{{ $provider['name'] }}</h3>
                            <p @class([
                                'mt-0.5 inline-flex items-center gap-1.5 text-xs',
                                $sourceText[$provider['accent']] => $connected,
                                'text-faint' => ! $connected,
                            ])>
                                <span @class(['h-1.5 w-1.5 rounded-full', $sourceDot[$provider['accent']] => $connected, 'bg-faint' => ! $connected])></span>
                                {{ $connected ? 'Connecté' : 'Non connecté' }}
                            </p>
                        </div>
                    </div>
                </div>

                @if ($connected)
                    <p class="mt-4 text-xs text-faint">
                        Dernière synchro :
                        <span class="text-muted">{{ $provider['lastSync']?->translatedFormat('d/m/Y H:i') ?? 'jamais' }}</span>
                    </p>
                    <form method="POST" action="{{ route('planning.sync', $provider['key']) }}" class="mt-4">
                        @csrf
                        <x-ui.neon-button type="submit" :variant="$provider['accent']" class="w-full">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-3l3 3M19 15a7 7 0 0 1-12 3l-3-3"/></svg>
                            Synchroniser
                        </x-ui.neon-button>
                    </form>
                @else
                    <p class="mt-4 text-xs leading-relaxed text-muted">Autorisez l'accès en lecture à votre agenda pour superposer ses événements.</p>
                    <x-ui.neon-button :href="route('planning.connect', $provider['key'])" :variant="$provider['accent']" class="mt-4 w-full">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-4.5M10 18H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4M8 12h8"/></svg>
                        Connecter {{ $provider['name'] }}
                    </x-ui.neon-button>
                @endif
            </x-ui.glass-card>
        @endforeach

        <x-ui.glass-card>
            <h3 class="font-display text-base font-semibold tracking-tight">Légende</h3>
            <div class="mt-4 flex flex-col gap-2.5">
                <div class="flex items-center gap-2.5 text-sm text-muted">
                    <span class="h-2.5 w-2.5 rounded-full bg-cyan"></span> Google Calendar
                </div>
                <div class="flex items-center gap-2.5 text-sm text-muted">
                    <span class="h-2.5 w-2.5 rounded-full bg-violet"></span> Outlook
                </div>
                <div class="flex items-center gap-2.5 text-sm text-muted">
                    <span class="h-2.5 w-2.5 rounded-full bg-lime"></span> Échéances financières
                </div>
            </div>
            <div class="neon-divider my-4"></div>
            <p class="text-xs text-faint">Événements sur la période</p>
            <p class="font-display text-2xl font-bold text-violet">{{ $eventsCount }}</p>
        </x-ui.glass-card>
    </section>

    {{-- Calendar + upcoming --}}
    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-ui.glass-card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-display text-lg font-semibold tracking-tight">Calendrier agrégé</h3>
                        <p class="mt-0.5 text-sm capitalize text-muted">{{ $periodLabel }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex rounded-xl border border-hairline p-0.5">
                            <button type="button" wire:click="setView('month')" @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                'bg-violet-soft text-violet' => $view === 'month',
                                'text-faint hover:text-ink' => $view !== 'month',
                            ])>Mois</button>
                            <button type="button" wire:click="setView('week')" @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                                'bg-violet-soft text-violet' => $view === 'week',
                                'text-faint hover:text-ink' => $view !== 'week',
                            ])>Semaine</button>
                        </div>
                        <button type="button" wire:click="previous" class="grid h-9 w-9 place-items-center rounded-xl border border-hairline text-faint transition hover:border-violet/40 hover:text-violet" aria-label="Période précédente">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <button type="button" wire:click="next" class="grid h-9 w-9 place-items-center rounded-xl border border-hairline text-faint transition hover:border-violet/40 hover:text-violet" aria-label="Période suivante">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-7 gap-1.5 text-center text-[0.65rem] font-semibold uppercase tracking-[0.15em] text-faint">
                    @foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>

                <div
                    wire:key="calendar-{{ $view }}-{{ $periodLabel }}"
                    wire:loading.delay.class="opacity-40"
                    class="mt-2 flex flex-col gap-1.5 transition-opacity"
                >
                    @foreach ($weeks as $week)
                        <div @class(['grid grid-cols-7 gap-1.5', 'min-h-[7rem]' => $view === 'week'])>
                            @foreach ($week as $cell)
                                @php $hasItems = count($cell['items']) > 0; @endphp
                                <div @class([
                                    'relative rounded-xl border p-1.5 text-left transition',
                                    'min-h-[4.75rem]' => $view === 'month',
                                    'min-h-[7rem]' => $view === 'week',
                                    'border-hairline bg-surface/40' => ! $cell['today'],
                                    'border-violet/50 bg-violet-soft' => $cell['today'],
                                    'opacity-35' => ! $cell['current'],
                                ])>
                                    <span @class([
                                        'text-xs font-medium',
                                        'text-violet' => $cell['today'],
                                        'text-faint' => ! $cell['today'],
                                    ])>{{ $cell['day'] }}</span>

                                    <div class="mt-1 flex flex-col gap-0.5">
                                        @foreach (array_slice($cell['items'], 0, $view === 'week' ? 5 : 3) as $item)
                                            @php $accent = $item->source->color(); @endphp
                                            <div
                                                x-data
                                                x-init="$el.animate([{ opacity: 0, transform: 'translateY(3px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 260, easing: 'ease-out' })"
                                                class="flex items-center gap-1 truncate rounded-md border px-1 py-0.5 text-[0.6rem] {{ $sourceChip[$accent] }}"
                                                title="{{ $item->title }}{{ $item->allDay ? '' : ' — '.$item->startsAt->format('H:i') }}"
                                            >
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $sourceDot[$accent] }}"></span>
                                                <span class="truncate">{{ $item->title }}</span>
                                            </div>
                                        @endforeach
                                        @php $extra = count($cell['items']) - ($view === 'week' ? 5 : 3); @endphp
                                        @if ($extra > 0)
                                            <span class="px-1 text-[0.6rem] text-faint">+{{ $extra }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </x-ui.glass-card>
        </div>

        <div>
            <x-ui.glass-card>
                <h3 class="font-display text-lg font-semibold tracking-tight">À venir</h3>
                <p class="mt-0.5 text-sm text-muted">Sur les 14 prochains jours.</p>

                <div class="mt-4 flex flex-col gap-2.5">
                    @forelse ($upcoming as $item)
                        @php $accent = $item->source->color(); @endphp
                        <div wire:key="upcoming-{{ $item->source->value }}-{{ $item->id }}" class="flex items-center gap-3 rounded-xl border border-hairline bg-surface/50 px-3 py-2.5">
                            <span class="h-2 w-2 shrink-0 rounded-full {{ $sourceDot[$accent] }}"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ $item->title }}</p>
                                <p class="text-xs text-faint">
                                    {{ $item->startsAt->translatedFormat('d M') }}{{ $item->allDay ? '' : ' · '.$item->startsAt->format('H:i') }} · {{ $item->source->label() }}
                                </p>
                            </div>
                            @if ($item->amount !== null)
                                <span class="shrink-0 text-sm font-semibold {{ $sourceText[$accent] }}">{{ number_format((float) $item->amount, 2, ',', ' ') }} €</span>
                            @elseif ($item->link !== null)
                                <a href="{{ $item->link }}" target="_blank" rel="noopener" class="shrink-0 text-faint transition hover:{{ $sourceText[$accent] }}" aria-label="Ouvrir l'événement">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5M19 5l-7 7M12 5H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="py-10 text-center text-sm text-faint">Aucun événement à venir. Connectez un agenda pour démarrer.</p>
                    @endforelse
                </div>
            </x-ui.glass-card>
        </div>
    </section>
</div>
