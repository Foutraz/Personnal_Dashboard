<?php

namespace Functional\Gamification\Notifications;

use Functional\Gamification\Models\Challenge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengeCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Challenge $challenge) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $replacements = [
            'name' => $this->challenge->template_key->label(),
            'target' => $this->challenge->template_key->unit()->format((float) $this->challenge->target_value),
            'xp' => $this->challenge->xp_reward,
        ];

        return [
            'title' => __('gamification::challenges.notification.completed_title', $replacements),
            'body' => __('gamification::challenges.notification.completed_body', $replacements),
            'challenge_id' => $this->challenge->id,
            'template_key' => $this->challenge->template_key->value,
            'xp_reward' => $this->challenge->xp_reward,
        ];
    }
}
