<?php

namespace Functional\RecurringExpenses\Rest\Controller;

use Functional\RecurringExpenses\Rest\Resource\RecurringExpenseResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class RecurringExpensesController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = RecurringExpenseResource::class;
}
