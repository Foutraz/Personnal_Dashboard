<div>
    @php
        $gainPositive = $global->absoluteGain >= 0;
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-tile
            label="Valeur du portefeuille"
            :value="number_format($global->currentValue, 0, ',', ' ')"
            unit="€"
            accent="cyan"
            :trend="$positions->count().' positions suivies'"
        />
        <x-ui.stat-tile
            label="Capital investi net"
            :value="number_format($global->netInvested, 0, ',', ' ')"
            unit="€"
            accent="violet"
            trend="Achats moins reventes"
        />
        <x-ui.glass-card hover padding="p-5">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Plus/moins-value latente</p>
            <div class="mt-4 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold {{ $gainPositive ? 'text-lime' : 'text-rose-400' }}">
                    {{ $gainPositive ? '+' : '' }}{{ number_format($global->absoluteGain, 0, ',', ' ') }}
                </span>
                <span class="text-sm text-muted">€</span>
            </div>
            <p class="mt-2 text-xs {{ $gainPositive ? 'text-lime' : 'text-rose-400' }}">
                {{ $gainPositive ? '+' : '' }}{{ number_format($global->percentageGain, 2, ',', ' ') }} % depuis l'achat
            </p>
        </x-ui.glass-card>
        <x-ui.glass-card hover padding="p-5">
            <p class="text-[0.7rem] font-medium uppercase tracking-[0.22em] text-faint">Plus-value réalisée</p>
            <div class="mt-4 flex items-baseline gap-1.5">
                <span class="font-display text-3xl font-bold {{ $realizedGain >= 0 ? 'text-lime' : 'text-rose-400' }}">
                    {{ $realizedGain >= 0 ? '+' : '' }}{{ number_format($realizedGain, 0, ',', ' ') }}
                </span>
                <span class="text-sm text-muted">€</span>
            </div>
            <p class="mt-2 text-xs text-muted">Cumul des ventes encaissées</p>
        </x-ui.glass-card>
    </div>

    <x-ui.glass-card class="mt-6">
        <h3 class="font-display text-lg font-semibold tracking-tight">Nouvelle position</h3>
        <p class="mt-0.5 text-sm text-muted">Ajoutez un actif, puis enregistrez vos achats et ventes.</p>

        <form wire:submit="createPosition" class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start">
            <div class="w-full lg:w-32">
                <input
                    type="text"
                    wire:model="newSymbol"
                    placeholder="Symbole"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm uppercase text-ink transition placeholder:text-faint focus:border-lime/40 focus:outline-none"
                />
                @error('newSymbol')<p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="flex-1">
                <input
                    type="text"
                    wire:model="newName"
                    placeholder="Nom de l'actif"
                    class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-lime/40 focus:outline-none"
                />
                @error('newName')<p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>@enderror
            </div>

            <div class="relative">
                <select
                    wire:model="newType"
                    class="appearance-none rounded-xl border border-hairline bg-surface px-4 py-2.5 pr-9 text-sm text-ink transition focus:border-lime/40 focus:outline-none"
                >
                    @foreach ($assetTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
                <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
            </div>

            <input
                type="number"
                step="any"
                wire:model="newCurrentPrice"
                placeholder="Prix actuel"
                class="w-full rounded-xl border border-hairline bg-surface px-4 py-2.5 text-sm text-ink transition placeholder:text-faint focus:border-lime/40 focus:outline-none lg:w-36"
            />

            <x-ui.neon-button type="submit" variant="lime">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                Ajouter
            </x-ui.neon-button>
        </form>
    </x-ui.glass-card>

    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($positions as $position)
            @php
                $value = $performance->positionValue($position);
                $invested = $performance->positionNetInvested($position);
                $result = $performance->positionPerformance($position);
                $up = $result->absoluteGain >= 0;
                $accents = ['cyan' => 'text-cyan', 'violet' => 'text-violet', 'lime' => 'text-lime'];
                $accentClass = $accents[$position->asset_type->color()] ?? $accents['cyan'];
            @endphp
            <x-ui.glass-card
                wire:key="position-{{ $position->id }}"
                hover
                padding="p-5"
                x-data="{}"
                x-init="$el.animate([{ opacity: 0, transform: 'translateY(10px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 360, easing: 'cubic-bezier(0.22,1,0.36,1)' })"
                class="group flex flex-col"
            >
                <div class="flex items-start justify-between">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-display text-lg font-bold tracking-tight {{ $accentClass }}">{{ $position->asset_symbol }}</span>
                            <span class="rounded-full border border-hairline px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wider text-faint">{{ $position->asset_type->label() }}</span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-muted">{{ $position->asset_name }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="deletePosition('{{ $position->id }}')"
                        wire:confirm="Supprimer cette position et son historique ?"
                        class="text-faint opacity-0 transition hover:text-rose-400 group-hover:opacity-100"
                        aria-label="Supprimer la position"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m-7 0v12a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V7"/></svg>
                    </button>
                </div>

                <div class="mt-4 flex items-baseline justify-between">
                    <span class="font-display text-2xl font-bold">{{ $position->hasCurrentPrice() ? number_format($value, 0, ',', ' ').' €' : '—' }}</span>
                    @if ($position->hasCurrentPrice())
                        <span class="text-sm font-medium {{ $up ? 'text-lime' : 'text-rose-400' }}">
                            {{ $up ? '+' : '' }}{{ number_format($result->percentageGain, 1, ',', ' ') }} %
                        </span>
                    @else
                        <span class="text-xs text-faint">Prix manquant</span>
                    @endif
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-xs text-muted">
                    <div>
                        <dt class="text-faint">Quantité</dt>
                        <dd class="font-medium text-ink">{{ rtrim(rtrim(number_format((float) $position->quantity, 4, ',', ' '), '0'), ',') }}</dd>
                    </div>
                    <div>
                        <dt class="text-faint">PRU</dt>
                        <dd class="font-medium text-ink">{{ number_format((float) $position->average_buy_price, 2, ',', ' ') }} €</dd>
                    </div>
                    <div>
                        <dt class="text-faint">Investi</dt>
                        <dd class="font-medium text-ink">{{ number_format($invested, 0, ',', ' ') }} €</dd>
                    </div>
                    <div>
                        <dt class="text-faint">+/- value</dt>
                        <dd class="font-medium {{ $up ? 'text-lime' : 'text-rose-400' }}">{{ $position->hasCurrentPrice() ? ($up ? '+' : '').number_format($result->absoluteGain, 0, ',', ' ').' €' : '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="startEditing('{{ $position->id }}')"
                        class="flex-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-muted transition hover:border-cyan/40 hover:text-cyan"
                    >
                        Prix
                    </button>
                    <button
                        type="button"
                        wire:click="startTransaction('{{ $position->id }}')"
                        class="flex-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-muted transition hover:border-lime/40 hover:text-lime"
                    >
                        Transaction
                    </button>
                </div>

                @if ($editingPositionId === $position->id)
                    <form wire:submit="updatePrice" class="mt-3 flex items-center gap-2">
                        <input
                            type="number"
                            step="any"
                            wire:model="editPrice"
                            placeholder="Prix actuel"
                            class="flex-1 rounded-lg border border-hairline bg-surface px-3 py-1.5 text-sm text-ink transition focus:border-cyan/40 focus:outline-none"
                        />
                        <x-ui.neon-button type="submit" variant="cyan" class="!px-3 !py-1.5 !text-xs">OK</x-ui.neon-button>
                    </form>
                @endif

                @if ($transactionPositionId === $position->id)
                    <form wire:submit="recordTransaction" class="mt-3 flex flex-col gap-2 rounded-xl border border-hairline bg-surface/50 p-3">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <select wire:model="txType" class="w-full appearance-none rounded-lg border border-hairline bg-surface px-3 py-1.5 pr-8 text-sm text-ink focus:border-lime/40 focus:outline-none">
                                    @foreach ($transactionTypes as $tType)
                                        <option value="{{ $tType->value }}">{{ $tType->label() }}</option>
                                    @endforeach
                                </select>
                                <svg class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m6 9 6 6 6-6"/></svg>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" step="any" wire:model="txQuantity" placeholder="Quantité" class="w-full rounded-lg border border-hairline bg-surface px-3 py-1.5 text-sm text-ink focus:border-lime/40 focus:outline-none" />
                            <input type="number" step="any" wire:model="txUnitPrice" placeholder="Prix unitaire" class="w-full rounded-lg border border-hairline bg-surface px-3 py-1.5 text-sm text-ink focus:border-lime/40 focus:outline-none" />
                        </div>
                        @error('txQuantity')<p class="text-xs text-rose-400">{{ $message }}</p>@enderror
                        @error('txUnitPrice')<p class="text-xs text-rose-400">{{ $message }}</p>@enderror
                        <x-ui.neon-button type="submit" variant="lime" class="!py-1.5 !text-xs">Enregistrer</x-ui.neon-button>
                    </form>
                @endif
            </x-ui.glass-card>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-ui.glass-card>
                    <p class="py-12 text-center text-sm text-faint">Aucune position. Ajoutez votre premier actif pour démarrer le suivi.</p>
                </x-ui.glass-card>
            </div>
        @endforelse
    </div>
</div>
