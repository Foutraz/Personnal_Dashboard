<?php

namespace Functional\Goals\Rest\Policies;

use Functional\Goals\Rest\Controls\GoalControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class GoalPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of goals.
     *
     * @var class-string<Control>
     */
    protected string $control = GoalControl::class;
}
