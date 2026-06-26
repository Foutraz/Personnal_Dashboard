<?php

namespace Functional\Todo\Rest\Policies;

use Functional\Todo\Rest\Controls\TaskControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class TaskPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of tasks.
     *
     * @var class-string<Control>
     */
    protected string $control = TaskControl::class;
}
