<?php

namespace Functional\Finance\Rest\Policies;

use Functional\Finance\Rest\Controls\PositionControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class PositionPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of positions.
     *
     * @var class-string<Control>
     */
    protected string $control = PositionControl::class;
}
