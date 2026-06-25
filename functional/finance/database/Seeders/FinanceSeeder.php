<?php

namespace Functional\Finance\Database\Seeders;

use Functional\Finance\Models\InvestmentTransaction;
use Functional\Finance\Models\Position;
use Illuminate\Database\Seeder;

class FinanceSeeder extends Seeder
{
    /**
     * Seed a handful of positions with their transactions.
     */
    public function run(): void
    {
        Position::factory()
            ->count(6)
            ->create()
            ->each(function (Position $position): void {
                InvestmentTransaction::factory()
                    ->count(3)
                    ->create([
                        'position_id' => $position->id,
                        'user_id' => $position->user_id,
                    ]);
            });
    }
}
