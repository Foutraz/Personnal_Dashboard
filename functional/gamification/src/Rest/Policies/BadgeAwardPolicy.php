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
}
