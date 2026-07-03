<?php

namespace Functional\Gamification\Listeners;

use Functional\Gamification\Models\PlayerProfile;
use Functional\Gamification\Models\XpEntry;
use Functional\Users\Events\UserDeleting;

class DeleteUserGamificationData
{
    /**
     * Delete the ledger entries and profile owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        XpEntry::query()->where('user_id', $event->user->id)->delete();
        PlayerProfile::query()->where('user_id', $event->user->id)->delete();
    }
}
