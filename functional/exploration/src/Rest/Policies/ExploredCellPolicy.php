<?php

namespace Functional\Exploration\Rest\Policies;

use Functional\Exploration\Rest\Controls\ExploredCellControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class ExploredCellPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of explored cells.
     *
     * @var class-string<Control>
     */
    protected string $control = ExploredCellControl::class;

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

    public function restore(Model $user, Model $model): bool
    {
        return false;
    }

    public function forceDelete(Model $user, Model $model): bool
    {
        return false;
    }
}
