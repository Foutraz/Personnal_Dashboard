<?php

namespace Functional\Exploration\Database\Seeders;

use Functional\Exploration\Models\ExploredCell;
use Illuminate\Database\Seeder;

class ExplorationSeeder extends Seeder
{
    /**
     * Seed a handful of explored cells.
     */
    public function run(): void
    {
        ExploredCell::factory()->count(20)->create();
    }
}
