<?php

namespace App\Policies;


use App\Access\Controls\SportActivityControl;
use Lomkit\Access\Policies\ControlledPolicy;

class SportActivityPolicy extends ControlledPolicy
{
    protected string $control = SportActivityControl::class;
}
