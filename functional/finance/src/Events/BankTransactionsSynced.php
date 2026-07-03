<?php

namespace Functional\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;

class BankTransactionsSynced
{
    use Dispatchable;

    public function __construct(public string $userId) {}
}
