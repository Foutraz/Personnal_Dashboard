<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\BadgeControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class BadgePolicy extends ControlledPolicy
{
    /**
     * The control opening the badge catalogue for reading.
     *
     * @var class-string<Control>
     */
    protected string $control = BadgeControl::class;

    /**
     * Forbid creating catalogue entries through the API.
     */
    public function create(Model $user): bool
    {
        return false;
    }

    /**
     * Forbid updating the synchronised catalogue through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting catalogue entries through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }
}
