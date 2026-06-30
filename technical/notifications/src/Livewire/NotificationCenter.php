<?php

namespace Technical\Notifications\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class NotificationCenter extends Component
{
    /**
     * Mark a single notification of the authenticated user as read.
     */
    public function markAsRead(string $id): void
    {
        $this->userNotifications()->where('id', $id)->first()?->markAsRead();
    }

    /**
     * Mark every unread notification of the authenticated user as read.
     */
    public function markAllAsRead(): void
    {
        $this->userNotifications()->whereNull('read_at')->update(['read_at' => Carbon::now()]);
    }

    /**
     * Expose the authenticated user's unread notification count.
     */
    public function getUnreadCountProperty(): int
    {
        return $this->userNotifications()->whereNull('read_at')->count();
    }

    /**
     * Expose the authenticated user's most recent notifications.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function getRecentProperty(): Collection
    {
        return $this->userNotifications()->latest()->limit(10)->get();
    }

    /**
     * Build the base query for the authenticated user's notifications.
     *
     * @return Builder<DatabaseNotification>
     */
    private function userNotifications(): Builder
    {
        return DatabaseNotification::query()->where('notifiable_id', auth('web')->id());
    }

    /**
     * Render the notification bell and dropdown panel.
     */
    public function render(): View
    {
        return view('notifications::livewire.notification-center');
    }
}
