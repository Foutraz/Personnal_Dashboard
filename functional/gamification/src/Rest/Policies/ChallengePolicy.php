<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\ChallengeControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class ChallengePolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of challenges.
     *
     * @var class-string<Control>
     */
    protected string $control = ChallengeControl::class;

    /**
     * Forbid creating challenges through the API.
     */
    public function create(Model $user): bool
    {
        return false;
    }

    /**
     * Forbid updating challenges through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting challenges through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid restoring challenges through the API.
     */
    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid purging challenges through the API.
     */
    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Restrict responding to the challenges owned by the authenticated user.
     */
    public function respond(Model $user, Model $model): bool
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }
}
