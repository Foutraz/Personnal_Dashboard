<?php

namespace Functional\Todo\Exceptions;

use Exception;

class TaskNotFoundException extends Exception
{
    /**
     * Build the exception for a task that could not be located for the user.
     */
    public static function forId(string $taskId): self
    {
        return new self("Task [{$taskId}] could not be found for the authenticated user.");
    }
}
