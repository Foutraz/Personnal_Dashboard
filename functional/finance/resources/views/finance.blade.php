<div>
    <x-slot:header>Finance</x-slot:header>

    <section class="reveal">
        <div class="flex flex-col gap-2">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.3em] text-lime">Module Finance</p>
            <h2 class="font-display text-3xl font-bold leading-tight sm:text-4xl">
                Votre <span class="text-lime">patrimoine</span> en trajectoire.
            </h2>
            <p class="max-w-2xl text-sm leading-relaxed text-muted">
                Suivez vos positions, mesurez votre performance, simulez vos versements programmés et projetez votre capital dans le futur.
            </p>
        </div>
    </section>

    <section class="mt-8" style="animation-delay: 0.05s;">
        @livewire('finance-portfolio-overview')
    </section>

    <section class="mt-8" style="animation-delay: 0.1s;">
        @livewire('finance-performance-chart')
    </section>

    <section class="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div>
            @livewire('finance-dca-simulator-panel')
        </div>
        <div>
            @livewire('finance-projections-panel')
        </div>
    </section>
</div>
