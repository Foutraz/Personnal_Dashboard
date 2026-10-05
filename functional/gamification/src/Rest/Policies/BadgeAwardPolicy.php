<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\BadgeAwardControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class BadgeAwardPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of badge awards.
     *
     * @var class-string<Control>
     */
    protected string $control = BadgeAwardControl::class;

    /**
     * Forbid creating ledger-backed awards through the API.
     */
    public function create(Model $user): bool
    {
        return false;
    }

    /**
     * Forbid updating definitive awards through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting definitive awards through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid restoring definitive awards through the API.
     */
    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid purging definitive awards through the API.
     */
    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid attaching a badge to an award through the API.
     */
    public function attachBadge(Model $user, Model $model, Model $badge): bool
    {
        return false;
    }

    /**
     * Forbid detaching a badge from an award through the API.
     */
    public function detachBadge(Model $user, Model $model, Model $badge): bool
    {
        return false;
    }
}
