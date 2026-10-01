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

    public function create(Model $user): bool
    {
        return false;
    }

    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    public function respond(Model $user, Model $model): bool
    {
        return $this->getControl()->applies($user, __FUNCTION__, $model);
    }
}
