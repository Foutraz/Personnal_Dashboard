<div>
    <x-slot:header>Santé</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-violet">Module Santé — Withings</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre <span class="text-violet text-glow-violet">corps</span> en données.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Connectez Withings, synchronisez vos mesures corporelles et suivez l'évolution de votre poids, composition et fréquence cardiaque.
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        <x-ui.glass-card hover class="relative overflow-hidden">
            <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-violet-soft opacity-60 blur-3xl"></div>

            <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-violet-soft text-violet">
                        <svg class="h-7 w-7" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.593c-5.63-5.539-11-10.297-11-14.402 0-3.791 3.068-5.191 5.281-5.191 1.312 0 4.151.501 5.719 4.457 1.59-3.968 4.464-4.447 5.726-4.447 2.54 0 5.274 1.621 5.274 5.181 0 4.069-5.136 8.625-11 14.402z"/>
                        </svg>
                    </span>

                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-display text-xl font-semibold tracking-tight">Withings</h3>
                            @if ($connection)
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                                    <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Connecté
                                </span>
                            @else
                                <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">
                                    Non connecté
                                </span>
                            @endif
                        </div>

                        @if ($connection)
                            <p class="mt-1.5 text-sm text-muted">
                                @if ($lastSyncedAt)
                                    Dernière synchronisation {{ $lastSyncedAt->diffForHumans() }}.
                                @else
                                    Connexion établie — lancez une première synchronisation.
                                @endif
                            </p>
                        @else
                            <p class="mt-1.5 text-sm text-muted">Liez votre compte Withings pour importer vos mesures corporelles.</p>
                        @endif
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($connection)
                        <form method="POST" action="{{ route('health.withings.sync') }}">
                            @csrf
                            <x-ui.neon-button type="submit" variant="violet">
                                <svg class="h-4 w-4 transition-transform duration-500 group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-2m2 8a7 7 0 0 1-12 2"/></svg>
                                Synchroniser
                            </x-ui.neon-button>
                        </form>
                    @else
                        <x-ui.neon-button :href="route('health.withings.connect')" variant="violet">
                            Connecter Withings
                            <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
                        </x-ui.neon-button>
                    @endif
                </div>
            </div>
        </x-ui.glass-card>
    </section>

    @if ($latestWeight)
        <section class="mt-8" style="animation-delay: 0.1s;">
            <x-ui.glass-card>
                <div class="flex flex-col gap-1">
                    <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-muted">Dernier poids enregistré</p>
                    <div class="flex items-baseline gap-2">
                        <span class="font-display text-4xl font-bold text-violet">{{ number_format($latestWeight->value, 1, ',', ' ') }}</span>
                        <span class="text-lg text-muted">kg</span>
                    </div>
                    <p class="text-sm text-faint">Mesuré {{ $latestWeight->measured_at->diffForHumans() }}</p>
                </div>
            </x-ui.glass-card>
        </section>
    @endif
</div>
