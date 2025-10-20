<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use App\Models\Team;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get departments first (idempotent)
        $itDept = Department::firstOrCreate([
            'name' => 'Information Technology'
        ], [
            'description' => 'IT Department'
        ]);

        $marketingDept = Department::firstOrCreate([
            'name' => 'Marketing'
        ], [
            'description' => 'Marketing Department'
        ]);

        $financeDept = Department::firstOrCreate([
            'name' => 'Finance'
        ], [
            'description' => 'Finance Department'
        ]);

        // Create or get teams (idempotent)
        $devTeam = Team::firstOrCreate([
            'name' => 'Development Team'
        ], [
            'description' => 'Software Development Team',
            'department_id' => $itDept->id
        ]);

        $marketingTeam = Team::firstOrCreate([
            'name' => 'Marketing Team'
        ], [
            'description' => 'Marketing and Sales Team',
            'department_id' => $marketingDept->id
        ]);

        // Create or update Admin user (preserve password if user exists)
        $admin = User::firstOrNew(['email' => 'admin@spendswift.com']);
        $isNew = !$admin->exists;
        if ($isNew) {
            $admin->password = Hash::make('password123');
        }
        $admin->name = 'System Administrator';
        $admin->role = 'ADMIN';
        $admin->status = 'active';
        $admin->department_id = $itDept->id;
        $admin->first_name = 'System';
        $admin->last_name = 'Administrator';
        $admin->save();

        // Create or update Final Manager (preserve password if exists)
        $finalManager = User::firstOrNew(['email' => 'john.smith@spendswift.com']);
        $isNew = !$finalManager->exists;
        if ($isNew) {
            $finalManager->password = Hash::make('password123');
        }
        $finalManager->name = 'John Smith';
        $finalManager->role = 'FINAL_MANAGER';
        $finalManager->status = 'active';
        $finalManager->department_id = $financeDept->id;
        $finalManager->first_name = 'John';
        $finalManager->last_name = 'Smith';
        $finalManager->position = 'Chief Financial Officer';
        $finalManager->save();

        // Create or update Direct Manager for Dev Team (preserve password if exists)
        $directManager = User::firstOrNew(['email' => 'sarah.johnson@spendswift.com']);
        $isNew = !$directManager->exists;
        if ($isNew) {
            $directManager->password = Hash::make('password123');
        }
        $directManager->name = 'Sarah Johnson';
        $directManager->role = 'DIRECT_MANAGER';
        $directManager->status = 'active';
        $directManager->team_id = $devTeam->id;
        $directManager->department_id = $itDept->id;
        $directManager->first_name = 'Sarah';
        $directManager->last_name = 'Johnson';
        $directManager->position = 'Development Team Lead';
        $directManager->save();

        // Update team manager (idempotent)
        $devTeam->update(['manager_id' => $directManager->id]);

        // Create or update Marketing Manager (preserve password if exists)
        $marketingManager = User::firstOrNew(['email' => 'ahmed.hassan@spendswift.com']);
        $isNew = !$marketingManager->exists;
        if ($isNew) {
            $marketingManager->password = Hash::make('password123');
        }
        $marketingManager->name = 'Ahmed Hassan';
        $marketingManager->role = 'DIRECT_MANAGER';
        $marketingManager->status = 'active';
        $marketingManager->team_id = $marketingTeam->id;
        $marketingManager->department_id = $marketingDept->id;
        $marketingManager->first_name = 'Ahmed';
        $marketingManager->last_name = 'Hassan';
        $marketingManager->position = 'Marketing Manager';
        $marketingManager->save();

        $marketingTeam->update(['manager_id' => $marketingManager->id]);

        // Create or update Accountant (preserve password if exists)
        $accountant = User::firstOrNew(['email' => 'lisa.chen@spendswift.com']);
        $isNew = !$accountant->exists;
        if ($isNew) {
            $accountant->password = Hash::make('password123');
        }
        $accountant->name = 'Lisa Chen';
        $accountant->role = 'ACCOUNTANT';
        $accountant->status = 'active';
        $accountant->department_id = $financeDept->id;
        $accountant->first_name = 'Lisa';
        $accountant->last_name = 'Chen';
        $accountant->position = 'Senior Accountant';
        $accountant->save();

        // Create or update a second Accountant for distribution (idempotent)
        $accountant2 = User::firstOrNew(['email' => 'omar.farid@spendswift.com']);
        $isNew2 = !$accountant2->exists;
        if ($isNew2) {
            $accountant2->password = Hash::make('password123');
        }
        $accountant2->name = 'Omar Farid';
        $accountant2->role = 'ACCOUNTANT';
        $accountant2->status = 'active';
        $accountant2->department_id = $financeDept->id;
        $accountant2->first_name = 'Omar';
        $accountant2->last_name = 'Farid';
        $accountant2->position = 'Accountant';
        $accountant2->save();

        // Create or update regular users
        $users = [
            [
                'name' => 'Mohamed Ali',
                'email' => 'mohamed.ali@spendswift.com',
                'team_id' => $devTeam->id,
                'department_id' => $itDept->id,
                'first_name' => 'Mohamed',
                'last_name' => 'Ali',
                'position' => 'Senior Developer'
            ],
            [
                'name' => 'Emma Wilson',
                'email' => 'emma.wilson@spendswift.com',
                'team_id' => $devTeam->id,
                'department_id' => $itDept->id,
                'first_name' => 'Emma',
                'last_name' => 'Wilson',
                'position' => 'Frontend Developer'
            ],
            [
                'name' => 'Omar Khalil',
                'email' => 'omar.khalil@spendswift.com',
                'team_id' => $marketingTeam->id,
                'department_id' => $marketingDept->id,
                'first_name' => 'Omar',
                'last_name' => 'Khalil',
                'position' => 'Marketing Specialist'
            ],
            [
                'name' => 'Fatima Al-Zahra',
                'email' => 'fatima.alzahra@spendswift.com',
                'team_id' => $marketingTeam->id,
                'department_id' => $marketingDept->id,
                'first_name' => 'Fatima',
                'last_name' => 'Al-Zahra',
                'position' => 'Content Creator'
            ]
        ];

        foreach ($users as $userData) {
            $u = User::firstOrNew(['email' => $userData['email']]);
            $isNewUser = !$u->exists;
            if ($isNewUser) {
                $u->password = Hash::make('password123');
            }
            $u->name = $userData['name'];
            $u->team_id = $userData['team_id'] ?? null;
            $u->department_id = $userData['department_id'] ?? null;
            $u->first_name = $userData['first_name'] ?? null;
            $u->last_name = $userData['last_name'] ?? null;
            $u->position = $userData['position'] ?? null;
            $u->role = 'USER';
            $u->status = 'active';
            $u->save();
        }

        // ==========================================
        // Sales Visit Management System Users
        // ==========================================
        
        // Create or update Sales Admin
        $salesAdmin = User::firstOrNew(['email' => 'admin@test.com']);
        $isNew = !$salesAdmin->exists;
        if ($isNew) {
            $salesAdmin->password = Hash::make('password');
        }
        $salesAdmin->name = 'Sales Admin';
        $salesAdmin->role = 'ADMIN';
        $salesAdmin->status = 'active';
        $salesAdmin->department_id = $marketingDept->id;
        $salesAdmin->first_name = 'Sales';
        $salesAdmin->last_name = 'Admin';
        $salesAdmin->position = 'Sales Manager';
        $salesAdmin->save();

        // Create or update Sales Representative 1
        $salesRep1 = User::firstOrNew(['email' => 'sales@test.com']);
        $isNew = !$salesRep1->exists;
        if ($isNew) {
            $salesRep1->password = Hash::make('password');
        }
        $salesRep1->name = 'Ahmed Sales Rep';
        $salesRep1->role = 'SALES_REP';
        $salesRep1->status = 'active';
        $salesRep1->team_id = $marketingTeam->id;
        $salesRep1->department_id = $marketingDept->id;
        $salesRep1->first_name = 'Ahmed';
        $salesRep1->last_name = 'Sales';
        $salesRep1->position = 'Sales Representative';
        $salesRep1->save();

        // Create or update Sales Representative 2
        $salesRep2 = User::firstOrNew(['email' => 'sales2@test.com']);
        $isNew = !$salesRep2->exists;
        if ($isNew) {
            $salesRep2->password = Hash::make('password');
        }
        $salesRep2->name = 'Mohamed Sales Rep';
        $salesRep2->role = 'SALES_REP';
        $salesRep2->status = 'active';
        $salesRep2->team_id = $marketingTeam->id;
        $salesRep2->department_id = $marketingDept->id;
        $salesRep2->first_name = 'Mohamed';
        $salesRep2->last_name = 'Sales';
        $salesRep2->position = 'Sales Representative';
        $salesRep2->save();

        $this->command->info('Users seeded successfully!');
        $this->command->info('');
        $this->command->info('=== SpendSwift Demo Accounts ===');
        $this->command->info('Admin: admin@spendswift.com / password123');
        $this->command->info('Final Manager: john.smith@spendswift.com / password123');
        $this->command->info('Direct Manager: sarah.johnson@spendswift.com / password123');
        $this->command->info('Accountant: lisa.chen@spendswift.com / password123');
        $this->command->info('Accountant: omar.farid@spendswift.com / password123');
        $this->command->info('User: mohamed.ali@spendswift.com / password123');
        $this->command->info('');
        $this->command->info('=== Sales Visit Management Accounts ===');
        $this->command->info('Sales Admin: admin@test.com / password');
        $this->command->info('Sales Rep 1: sales@test.com / password');
        $this->command->info('Sales Rep 2: sales2@test.com / password');
    }
}
