<?php

namespace Functional\Users\Rest\Policies;

use Functional\Users\Rest\Controls\UserControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class UserPolicy extends ControlledPolicy
{
    /**
     * The control restricting a user to their own record.
     *
     * @var class-string<Control>
     */
    protected string $control = UserControl::class;
}
