<?php

namespace Functional\Planning\Database\Seeders;

use Functional\Planning\Models\CalendarEvent;
use Illuminate\Database\Seeder;

class PlanningSeeder extends Seeder
{
    /**
     * Seed a handful of calendar events.
     */
    public function run(): void
    {
        CalendarEvent::factory()->count(15)->create();
    }
}
