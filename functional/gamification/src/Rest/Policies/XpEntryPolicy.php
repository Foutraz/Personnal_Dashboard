<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\XpEntryControl;
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
}
