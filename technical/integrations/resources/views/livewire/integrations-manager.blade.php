<div>
    <x-slot:header>Intégrations</x-slot:header>

    <section class="reveal flex flex-col gap-2">
        <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Connexions</p>
        <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
            Vos <span class="text-cyan text-glow-cyan">services externes</span>.
        </h2>
        <p class="max-w-2xl text-sm leading-relaxed text-muted">
            Reliez vos comptes pour laisser le tableau de bord agréger vos données. Connectez, synchronisez ou révoquez l'accès à tout moment.
        </p>
    </section>

    @if (session('integrations.status'))
        <div class="mt-6 rounded-xl border border-lime/30 bg-lime-soft px-4 py-3 text-sm text-lime reveal">
            {{ session('integrations.status') }}
        </div>
    @endif

    <section class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-2">
        @foreach ($cards as $card)
            <x-ui.glass-card hover class="relative overflow-hidden">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4">
                        @if ($card['icon'] === 'strava')
                            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-[#fc4c02]/15 text-[#fc4c02]">
                                <svg class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24"><path d="M15.387 17.944l-2.089-4.116h-3.065L15.387 24l5.15-10.172h-3.066m-7.008-5.599l2.836 5.598h4.172L10.463 0l-7 13.827h4.172"/></svg>
                            </span>
                        @else
                            <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-{{ $card['accent'] }}-soft text-{{ $card['accent'] }}">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/></svg>
                            </span>
                        @endif

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-display text-xl font-semibold tracking-tight">{{ $card['label'] }}</h3>
                                @if (! $card['available'])
                                    <span class="rounded-full border border-violet/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-violet">Bientôt</span>
                                @elseif ($card['connected'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                                        <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Connecté
                                    </span>
                                @else
                                    <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">Non connecté</span>
                                @endif
                            </div>
                            <p class="mt-1.5 text-sm text-muted">{{ $card['description'] }}</p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-3">
                        @if (! $card['available'])
                            <x-ui.neon-button variant="ghost" :disabled="true">Indisponible</x-ui.neon-button>
                        @elseif ($card['connected'])
                            <form method="POST" action="{{ route($card['sync_route']) }}">
                                @csrf
                                <x-ui.neon-button type="submit" variant="cyan">
                                    <svg class="h-4 w-4 transition-transform duration-500 group-hover:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a7 7 0 0 1 12-2m2 8a7 7 0 0 1-12 2"/></svg>
                                    Synchroniser
                                </x-ui.neon-button>
                            </form>
                            <x-ui.neon-button
                                type="button"
                                variant="ghost"
                                wire:click="disconnect('{{ $card['provider'] }}')"
                                wire:confirm="Déconnecter {{ $card['label'] }} ? Les données importées seront supprimées."
                            >
                                Déconnecter
                            </x-ui.neon-button>
                        @else
                            <x-ui.neon-button :href="route($card['connect_route'])" variant="lime">
                                Connecter
                                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
                            </x-ui.neon-button>
                        @endif
                    </div>
                </div>
            </x-ui.glass-card>
        @endforeach
    </section>

    <section class="mt-10">
        <h3 class="mb-5 font-display text-lg font-semibold tracking-tight">Compte</h3>

        <x-ui.glass-card>
            <div class="flex items-start gap-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-surface-2">
                    <svg class="h-6 w-6" viewBox="0 0 24 24"><path fill="#EA4335" d="M12 5c1.6 0 3 .55 4.1 1.6L19 3.8C17.1 2 14.7 1 12 1 7.7 1 4 3.5 2.2 7.1l3.4 2.6C6.5 6.9 9 5 12 5Z"/><path fill="#4285F4" d="M23 12.3c0-.8-.1-1.5-.2-2.3H12v4.5h6.2c-.3 1.4-1.1 2.6-2.3 3.4l3.5 2.7C21.6 18.6 23 15.8 23 12.3Z"/><path fill="#FBBC05" d="M5.6 14.3c-.2-.7-.4-1.4-.4-2.3s.1-1.6.4-2.3L2.2 7.1C1.4 8.6 1 10.3 1 12s.4 3.4 1.2 4.9l3.4-2.6Z"/><path fill="#34A853" d="M12 23c2.7 0 5-.9 6.7-2.4l-3.5-2.7c-.9.6-2.1 1-3.2 1-3 0-5.5-1.9-6.4-4.6l-3.4 2.6C4 20.5 7.7 23 12 23Z"/></svg>
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h4 class="font-display text-base font-semibold tracking-tight">Compte Google</h4>
                        @if ($googleAccount?->google_id)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-lime/30 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-lime">
                                <span class="h-1.5 w-1.5 rounded-full bg-lime"></span> Lié
                            </span>
                        @else
                            <span class="rounded-full border border-hairline px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wider text-faint">Non lié</span>
                        @endif
                    </div>
                    @if ($googleAccount?->google_id)
                        <p class="mt-1.5 text-sm text-muted">Connexion liée à {{ $googleAccount->email }}.</p>
                    @else
                        <p class="mt-1.5 text-sm text-muted">Utilisez « Continuer avec Google » à la connexion pour lier votre compte.</p>
                    @endif
                </div>
            </div>
        </x-ui.glass-card>
    </section>
</div>
