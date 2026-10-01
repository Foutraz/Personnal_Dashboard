<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\PlayerProfileControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class PlayerProfilePolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of player profiles.
     *
     * @var class-string<Control>
     */
    protected string $control = PlayerProfileControl::class;

    /**
     * Forbid creating ledger-derived records through the API.
     */
    public function create(Model $user): bool
    {
        return false;
    }

    /**
     * Forbid updating the recomputed projection through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting the recomputed projection through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid restoring the recomputed projection through the API.
     */
    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid purging the recomputed projection through the API.
     */
    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }
}
