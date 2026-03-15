<?php

namespace App\Policies;

use App\Access\Controls\UserControl;
use Lomkit\Access\Policies\ControlledPolicy;

class UserPolicy extends ControlledPolicy
{
    protected string $control = UserControl::class;
}
