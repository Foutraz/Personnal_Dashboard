<div x-data="{ open: false }" class="relative" wire:poll.30s>
    <button
        @click="open = ! open"
        class="relative grid h-10 w-10 place-items-center rounded-full border border-hairline text-muted transition hover:border-cyan/40 hover:text-ink"
        aria-label="Notifications"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 grid h-5 min-w-[1.25rem] place-items-center rounded-full bg-cyan px-1 text-[0.65rem] font-bold text-black">{{ $this->unreadCount }}</span>
        @endif
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition.origin.top.right
        class="glass absolute right-0 z-30 mt-2 w-80 overflow-hidden p-2"
        style="display: none;"
    >
        <div class="flex items-center justify-between px-3 py-2">
            <p class="text-sm font-semibold">Notifications</p>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllAsRead" class="text-xs text-cyan transition hover:text-ink">Tout marquer comme lu</button>
            @endif
        </div>
        <div class="neon-divider my-1"></div>

        <div class="max-h-96 overflow-y-auto">
            @forelse ($this->recent as $notification)
                <button
                    wire:click="markAsRead('{{ $notification->id }}')"
                    class="flex w-full flex-col gap-0.5 rounded-lg px-3 py-2 text-left transition hover:bg-cyan-soft {{ $notification->read_at ? 'opacity-60' : '' }}"
                >
                    <span class="flex items-center gap-2 text-sm font-medium">
                        @unless ($notification->read_at)<span class="h-1.5 w-1.5 rounded-full bg-cyan"></span>@endunless
                        {{ $notification->data['title'] ?? $notification->data['label'] ?? class_basename($notification->type) }}
                    </span>
                    <span class="text-xs text-faint">
                        @if (isset($notification->data['amount'])){{ $notification->data['amount'] }} {{ $notification->data['currency'] ?? '' }} · @endif
                        {{ $notification->created_at?->diffForHumans() }}
                    </span>
                </button>
            @empty
                <p class="px-3 py-6 text-center text-sm text-faint">Aucune notification.</p>
            @endforelse
        </div>
    </div>
</div>
