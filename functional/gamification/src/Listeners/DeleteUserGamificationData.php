<?php

namespace Functional\Gamification\Listeners;

use Functional\Gamification\Models\BadgeAward;
use Functional\Gamification\Models\Challenge;
use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\Streak;
use Functional\Gamification\Models\XpEntry;
use Functional\Gamification\Notifications\BadgeAwardedNotification;
use Functional\Users\Events\UserDeleting;

class DeleteUserGamificationData
{
    /**
     * Delete the badge awards, badge notifications, challenges, ledger entries and profile owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        BadgeAward::query()->whereBelongsTo($event->user)->delete();
        Challenge::query()->whereBelongsTo($event->user)->delete();
        $event->user->notifications()->where('type', BadgeAwardedNotification::class)->delete();
        XpEntry::query()->where('user_id', $event->user->id)->delete();
        Streak::query()->where('user_id', $event->user->id)->delete();
        PlayerProfile::query()->where('user_id', $event->user->id)->delete();
    }
}
