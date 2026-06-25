<div>
    <x-slot:header>Sport</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Module Sport — Strava</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Vos <span class="text-cyan text-glow-cyan">performances</span> en orbite.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Connectez Strava, synchronisez vos activités et explorez vos statistiques en temps réel sur votre command deck.
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        @livewire('connect-strava')
    </section>

    <section class="mt-8" style="animation-delay: 0.1s;">
        @livewire('sport-statistics')
    </section>

    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            @livewire('activities-history')
        </div>
        <div>
            @livewire('performance-analysis')
        </div>
    </section>
</div>
