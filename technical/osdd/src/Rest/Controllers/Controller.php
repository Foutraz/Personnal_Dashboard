<?php

namespace Technical\Osdd\Rest\Controllers;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Http\Controllers\Controller as RestController;
use Lomkit\Rest\Http\Requests\MutateRequest;

abstract class Controller extends RestController
{
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
