<?php

namespace Functional\RecurringExpenses\Exceptions;

use Exception;

class RecurringExpenseNotFoundException extends Exception
{
    /**
     * Build the exception for a recurring expense that could not be located for the user.
     */
    public static function forId(string $expenseId): self
    {
        return new self("Recurring expense [{$expenseId}] could not be found for the authenticated user.");
    }
}
