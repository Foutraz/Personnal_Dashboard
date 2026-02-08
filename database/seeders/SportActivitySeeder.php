<?php

namespace Database\Seeders;

use App\Models\SportActivity;
use Illuminate\Database\Seeder;

class SportActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SportActivity::factory(100)->create();
    }
}
