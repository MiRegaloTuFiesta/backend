<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User Creator',
            'email' => 'creador@eventwish.local',
            'password' => bcrypt('password'),
            'role' => 'creator',
        ]);

        User::factory()->create([
            'name' => 'Admin Sistema',
            'email' => 'admin@eventwish.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->call(SettingsSeeder::class);
    }
}
