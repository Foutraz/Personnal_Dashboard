<?php

namespace Functional\Exploration\Listeners;

use Functional\Exploration\Models\ExploredCell;
use Functional\Users\Events\UserDeleting;

class DeleteUserExploredCells
{
    /**
     * Delete the explored cells owned by the deleting user.
     */
    public function handle(UserDeleting $event): void
    {
        ExploredCell::query()
            ->where('user_id', $event->user->id)
            ->cursor()
            ->each(fn (ExploredCell $cell) => $cell->delete());
    }
}
