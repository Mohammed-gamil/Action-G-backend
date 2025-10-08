<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\InventoryItem;
use App\Models\User;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find a manager or admin to be the creator
        $manager = User::where('role', 'ADMIN')
            ->orWhere('role', 'DIRECT_MANAGER')
            ->orWhere('role', 'FINAL_MANAGER')
            ->first();

        if (!$manager) {
            $this->command->warn('No manager/admin user found. Please create one first.');
            return;
        }

        $items = [
            [
                'name' => 'Laptop Dell XPS 15',
                'description' => 'High-performance laptop for development work',
                'category' => 'Electronics',
                'quantity' => 10,
                'unit' => 'piece',
                'unit_cost' => 5000.00,
                'location' => 'Main Office - IT Storage',
                'condition' => 'good',
            ],
            [
                'name' => 'Projector Epson EB-2250U',
                'description' => 'Full HD projector for presentations',
                'category' => 'Electronics',
                'quantity' => 5,
                'unit' => 'piece',
                'unit_cost' => 3500.00,
                'location' => 'Conference Room',
                'condition' => 'good',
            ],
            [
                'name' => 'Camera Canon EOS 90D',
                'description' => 'Professional DSLR camera with lens kit',
                'category' => 'Photography',
                'quantity' => 3,
                'unit' => 'piece',
                'unit_cost' => 8000.00,
                'location' => 'Media Department',
                'condition' => 'good',
            ],
            [
                'name' => 'Microphone Rode NT-USB',
                'description' => 'USB condenser microphone',
                'category' => 'Audio Equipment',
                'quantity' => 8,
                'unit' => 'piece',
                'unit_cost' => 800.00,
                'location' => 'Recording Studio',
                'condition' => 'good',
            ],
            [
                'name' => 'Tripod Manfrotto MT055',
                'description' => 'Professional aluminum tripod',
                'category' => 'Photography',
                'quantity' => 6,
                'unit' => 'piece',
                'unit_cost' => 1200.00,
                'location' => 'Equipment Room',
                'condition' => 'good',
            ],
            [
                'name' => 'External Hard Drive 2TB',
                'description' => 'Portable storage device',
                'category' => 'Electronics',
                'quantity' => 15,
                'unit' => 'piece',
                'unit_cost' => 350.00,
                'location' => 'IT Storage',
                'condition' => 'good',
            ],
            [
                'name' => 'Whiteboard Marker Set',
                'description' => 'Pack of 12 assorted colors',
                'category' => 'Office Supplies',
                'quantity' => 50,
                'unit' => 'pack',
                'unit_cost' => 45.00,
                'location' => 'Supply Cabinet',
                'condition' => 'good',
            ],
            [
                'name' => 'Extension Cable 10m',
                'description' => '10-meter heavy-duty extension cable',
                'category' => 'Electrical',
                'quantity' => 12,
                'unit' => 'piece',
                'unit_cost' => 150.00,
                'location' => 'Equipment Room',
                'condition' => 'good',
            ],
            [
                'name' => 'Lighting Kit LED Panel',
                'description' => 'Professional LED lighting panel with stand',
                'category' => 'Photography',
                'quantity' => 4,
                'unit' => 'set',
                'unit_cost' => 2500.00,
                'location' => 'Studio',
                'condition' => 'fair',
            ],
            [
                'name' => 'Printer HP LaserJet Pro',
                'description' => 'Black and white laser printer',
                'category' => 'Electronics',
                'quantity' => 2,
                'unit' => 'piece',
                'unit_cost' => 1800.00,
                'location' => 'Print Room',
                'condition' => 'needs_maintenance',
                'last_maintenance_date' => now()->subMonths(2),
            ],
        ];

        foreach ($items as $itemData) {
            InventoryItem::create([
                ...$itemData,
                'reserved_quantity' => 0,
                'is_active' => true,
                'added_by' => $manager->id,
                'updated_by' => $manager->id,
            ]);
        }

        $this->command->info('✓ Inventory items seeded successfully!');
    }
}
