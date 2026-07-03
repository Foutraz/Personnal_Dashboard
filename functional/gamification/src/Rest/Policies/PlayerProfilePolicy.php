<?php

namespace Functional\Gamification\Rest\Policies;

use Functional\Gamification\Rest\Controls\PlayerProfileControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class PlayerProfilePolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of player profiles.
     *
     * @var class-string<Control>
     */
    protected string $control = PlayerProfileControl::class;
}
