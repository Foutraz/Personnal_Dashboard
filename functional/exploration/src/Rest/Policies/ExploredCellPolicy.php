<?php

namespace Functional\Exploration\Rest\Policies;

use Functional\Exploration\Rest\Controls\ExploredCellControl;
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
}
