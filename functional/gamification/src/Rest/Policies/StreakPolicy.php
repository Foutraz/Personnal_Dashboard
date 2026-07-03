<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\StreakControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class StreakPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of streaks.
     *
     * @var class-string<Control>
     */
    protected string $control = StreakControl::class;

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
}
