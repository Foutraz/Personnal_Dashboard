<?php

namespace Functional\Finance\Console;

use Functional\Finance\Actions\RefreshPositionPrices;
use Illuminate\Console\Command;

class RefreshMarketPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:refresh-market-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the current price of all crypto positions from the market data API.';

    /**
     * Invoke the refresh action and report the number of positions updated.
     */
    public function handle(RefreshPositionPrices $action): int
    {
        $count = $action();

        $this->line("Updated {$count} position(s) with current market prices.");

        return self::SUCCESS;
    }
}
