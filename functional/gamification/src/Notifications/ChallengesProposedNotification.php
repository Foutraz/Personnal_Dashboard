<?php

namespace Functional\Gamification\Notifications;

use Functional\Gamification\Services\Dto\GamificationWeek;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengesProposedNotification extends Notification
{
    use Queueable;

    public function __construct(public GamificationWeek $week, public int $count) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => trans_choice('gamification::challenges.notification.proposed_title', $this->count),
            'body' => __('gamification::challenges.notification.proposed_body'),
            'week_key' => $this->week->key(),
            'count' => $this->count,
        ];
    }
}
