<?php

namespace Functional\Sport\Rest\Policies;

use Functional\Sport\Rest\Controls\SportActivityControl;
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
}
