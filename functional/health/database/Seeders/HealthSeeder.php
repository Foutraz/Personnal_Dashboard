<?php

namespace Functional\Health\Database\Seeders;

use Functional\Health\Models\BodyMeasurement;
use Illuminate\Database\Seeder;

class HealthSeeder extends Seeder
{
    /**
     * Seed a handful of body measurements.
     */
    public function run(): void
    {
        BodyMeasurement::factory()->count(20)->create();
    }
}
