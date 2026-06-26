<?php

namespace Functional\Moto\Database\Seeders;

use Functional\Moto\Models\MotoRide;
use Illuminate\Database\Seeder;

class MotoSeeder extends Seeder
{
    /**
     * Seed a handful of moto rides.
     */
    public function run(): void
    {
        MotoRide::factory()->count(5)->create();

        MotoRide::factory()->create([
            'title' => 'Sortie cols alpins',
            'started_at' => now()->subWeek(),
            'duration' => 18000,
            'distance' => 320.50,
            'weather_label' => 'Excellent',
        ]);
    }
}
