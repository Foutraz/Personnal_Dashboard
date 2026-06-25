<?php

namespace Functional\Goals\Database\Seeders;

use Functional\Goals\Models\Goal;
use Illuminate\Database\Seeder;

class GoalsSeeder extends Seeder
{
    /**
     * Seed a handful of goals across the available types.
     */
    public function run(): void
    {
        Goal::factory()->count(2)->sportDistance()->create();
        Goal::factory()->count(2)->manual()->create();
        Goal::factory()->count(2)->create();
    }
}
