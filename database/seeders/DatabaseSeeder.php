<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\UserSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the main UserSeeder which creates Admin, Managers and Accountant idempotently
        $this->call(UserSeeder::class);

        // Ensure the test user exists (idempotent) and preserve password when the user already exists
        $testUser = User::firstOrNew(['email' => 'test@example.com']);
        $isNewTestUser = !$testUser->exists;
        if ($isNewTestUser) {
            $testUser->password = Hash::make('password123');
        }
        $testUser->name = 'Test User';
        $testUser->save();

        // Create a few demo users if there are very few users in the DB
        $current = User::count();
        $desired = 5;
        if ($current < $desired) {
            User::factory()->count($desired - $current)->create();
        }

        // Seed departments and teams
        $this->call([
            DepartmentSeeder::class,
            TeamSeeder::class,
        ]);

        // Seed inventory items
        $this->call(InventorySeeder::class);

        // Seed sales visit data (clients, business types, sample visits)
        $this->call([
            SalesVisitSeeder::class,
            SampleVisitsSeeder::class,
        ]);
    }
}
