<div>
    <x-slot:header>Dashboard</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-cyan">Bonjour {{ auth()->user()?->name }}</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre <span class="text-cyan text-glow-cyan">command deck</span> personnel.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Tous vos modules réunis sur une seule surface, agrégés en temps réel.
            </p>
        </div>
    </section>

    <section class="mt-10">
        <div class="mb-5 flex items-center justify-between">
            <h3 class="font-display text-lg font-semibold tracking-tight">Modules</h3>
            <span class="text-xs text-faint">{{ $summaries->count() }} modules</span>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($summaries as $index => $summary)
                <x-ui.module-card
                    :title="$summary->title"
                    :accent="$summary->accent"
                    :available="$summary->available"
                    :href="$summary->href"
                    :icon-path="$summary->icon"
                    :metric-value="$summary->metricValue"
                    :metric-unit="$summary->metricUnit"
                    :secondary-lines="$summary->secondaryLines"
                    :call-to-action="$summary->callToAction"
                    :delay="$index * 60"
                />
            @endforeach
        </div>
    </section>
</div>
