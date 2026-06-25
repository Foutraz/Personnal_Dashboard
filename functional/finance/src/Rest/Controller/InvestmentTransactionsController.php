<?php

namespace Functional\Finance\Rest\Controller;

use Functional\Finance\Rest\Resource\InvestmentTransactionResource;
use Lomkit\Rest\Http\Resource;
use Technical\Osdd\Rest\Controllers\Controller;

class InvestmentTransactionsController extends Controller
{
    /**
     * The resource the controller corresponds to.
     *
     * @var class-string<resource>
     */
    public static $resource = InvestmentTransactionResource::class;
}
