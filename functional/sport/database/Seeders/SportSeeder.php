<?php

namespace Functional\Sport\Database\Seeders;

use Functional\Sport\Models\SportActivity;
use Illuminate\Database\Seeder;

class SportSeeder extends Seeder
{
    /**
     * Seed a handful of sport activities.
     */
    public function run(): void
    {
        SportActivity::factory()->count(20)->create();
    }
}
