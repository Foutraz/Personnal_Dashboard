<?php

namespace Functional\Finance\Rest\Policies;

use Functional\Finance\Rest\Controls\InvestmentTransactionControl;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Policies\ControlledPolicy;

class InvestmentTransactionPolicy extends ControlledPolicy
{
    /**
     * The control enforcing per-user ownership of investment transactions.
     *
     * @var class-string<Control>
     */
    protected string $control = InvestmentTransactionControl::class;
}
