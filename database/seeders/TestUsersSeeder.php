<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Password: "password" (bcrypt hashed)
        $password = Hash::make('password');

        $users = [
            [
                'name' => 'Ahmed Sales Rep',
                'email' => 'sales@test.com',
                'password' => $password,
                'role' => 'SALES_REP',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Admin User',
                'email' => 'admin@test.com',
                'password' => $password,
                'role' => 'ADMIN',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mohamed Sales Rep',
                'email' => 'sales2@test.com',
                'password' => $password,
                'role' => 'SALES_REP',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@test.com',
                'password' => $password,
                'role' => 'SUPER_ADMIN',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            // Check if user already exists
            $exists = DB::table('users')->where('email', $user['email'])->exists();
            
            if (!$exists) {
                DB::table('users')->insert($user);
                $this->command->info("Created user: {$user['email']}");
            } else {
                $this->command->warn("User already exists: {$user['email']}");
            }
        }

        $this->command->info('Test users seeded successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('  Sales Rep: sales@test.com / password');
        $this->command->info('  Admin: admin@test.com / password');
        $this->command->info('  Sales Rep 2: sales2@test.com / password');
        $this->command->info('  Super Admin: superadmin@test.com / password');
    }
}
