<?php

namespace Functional\Sport\Rest\Policies;

use Functional\Sport\Rest\Controls\SportActivityControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class SportActivityPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of sport activities.
     *
     * @var class-string<Control>
     */
    protected string $control = SportActivityControl::class;

    public function delete(Model $user, Model $model): bool
    {
        return false;
    }

    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }
}
