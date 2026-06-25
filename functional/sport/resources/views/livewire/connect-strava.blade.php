<div>
    <x-ui.glass-card hover class="relative overflow-hidden">
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-cyan-soft opacity-60 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#fc4c02]/15 text-[#fc4c02]">
                    <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.827h4.172"/></svg>
                </span>

                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="font-display text-xl font-semibold tracking-tight">Strava</h3>
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
                        <p class="mt-1.5 text-sm text-muted">Liez votre compte Strava pour importer vos activités.</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                @if ($connection)
                    <form method="POST" action="{{ route('sport.strava.sync') }}">
                        @csrf
                        <x-ui.neon-button type="submit" variant="cyan">
                            <svg class="h-4 w-4 transition-transform duration-500 group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-2m2 8a7 7 0 0 1-12 2"/></svg>
                            Synchroniser
                        </x-ui.neon-button>
                    </form>
                @else
                    <x-ui.neon-button :href="route('sport.strava.connect')" variant="lime">
                        Connecter Strava
                        <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
                    </x-ui.neon-button>
                @endif
            </div>
        </div>
    </x-ui.glass-card>
</div>
