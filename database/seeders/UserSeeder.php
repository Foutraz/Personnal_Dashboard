<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(5)->create()->each(function ($user) {
            $user->assignRole('user');
        });

        User::factory()->create([
            'name' => 'Quentin Mari',
            'email' => config('app.admin_email'),
            'password' => Hash::make(config('app.admin_password')),
            'email_verified_at' => now(),
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'test simple user',
            'email' => 'test@test.fr',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ])->assignRole('user');
    }
}
