<?php

namespace Technical\Osdd\Rest\Controllers;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Http\Controllers\Controller as RestController;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Technical\Osdd\Rest\Middleware\LimitMutateOperations;
use Technical\Osdd\Rest\Throttle\RestRateLimit;

abstract class Controller extends RestController
{
    public function __construct()
    {
        $this->middleware(RestRateLimit::middleware());
        $this->middleware(LimitMutateOperations::class)->only(['mutate', 'destroy', 'restore', 'forceDelete']);
    }

    /**
     * Mutate resources while guaranteeing the mutation transaction never leaks on authorization failure.
     */
    public function mutate(MutateRequest $request)
    {
        $initialTransactionLevel = DB::transactionLevel();

        try {
            return parent::mutate($request);
        } finally {
            while (DB::transactionLevel() > $initialTransactionLevel) {
                DB::rollBack();
            }
        }
    }
}
