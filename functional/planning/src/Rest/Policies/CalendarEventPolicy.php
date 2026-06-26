<?php

namespace Functional\Planning\Rest\Policies;

use Functional\Planning\Rest\Controls\CalendarEventControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class CalendarEventPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of calendar events.
     *
     * @var class-string<Control>
     */
    protected string $control = CalendarEventControl::class;
}
