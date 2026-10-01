<?php

namespace Functional\Gamification\Notifications;

use Functional\Gamification\Models\Badge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BadgeAwardedNotification extends Notification
{
    use Queueable;

    /**
     * Create a notification announcing the awarded badge.
     */
    public function __construct(public Badge $badge) {}

    /**
     * Get the delivery channels of the notification.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Build the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $replacements = [
            'name' => $this->badge->name(),
            'tier' => $this->badge->tier->label(),
            'xp' => $this->badge->xp_reward,
        ];

        return [
            'title' => __('gamification::badges.notification.title', $replacements),
            'body' => __('gamification::badges.notification.body', $replacements),
            'badge_key' => $this->badge->key,
            'tier' => $this->badge->tier->value,
            'xp_reward' => $this->badge->xp_reward,
        ];
    }
}
