<?php

namespace Functional\Todo\Database\Seeders;

use Functional\Todo\Models\Task;
use Illuminate\Database\Seeder;

class TodoSeeder extends Seeder
{
    /**
     * Seed a handful of tasks.
     */
    public function run(): void
    {
        Task::factory()->count(12)->create();
        Task::factory()->completed()->count(4)->create();
    }
}
