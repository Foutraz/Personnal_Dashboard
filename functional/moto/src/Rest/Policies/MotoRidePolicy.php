<?php

namespace Functional\Moto\Rest\Policies;

use Functional\Moto\Rest\Controls\MotoRideControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class MotoRidePolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of moto rides.
     *
     * @var class-string<Control>
     */
    protected string $control = MotoRideControl::class;
}
