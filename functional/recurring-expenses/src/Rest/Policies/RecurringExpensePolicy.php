<?php

namespace Functional\RecurringExpenses\Rest\Policies;

use Functional\RecurringExpenses\Rest\Controls\RecurringExpenseControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class RecurringExpensePolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of recurring expenses.
     *
     * @var class-string<Control>
     */
    protected string $control = RecurringExpenseControl::class;
}
