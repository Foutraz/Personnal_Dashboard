<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\XpEntryControl;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class XpEntryPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of xp entries.
     *
     * @var class-string<Control>
     */
    protected string $control = XpEntryControl::class;

    /**
     * Forbid updating the append-only ledger through the API.
     */
    public function update(Model $user, Model $model): bool
    {
        return false;
    }

    /**
     * Forbid deleting from the append-only ledger through the API.
     */
    public function delete(Model $user, Model $model): bool
    {
        return false;
    }
}
