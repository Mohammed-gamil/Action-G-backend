<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_inventory_requests', function (Blueprint $table) {
            // Employee Information
            $table->string('employee_name')->nullable()->after('description');
            $table->string('employee_position')->nullable()->after('employee_name');
            $table->string('employee_phone')->nullable()->after('employee_position');
            
            // Exit Details
            $table->enum('exit_purpose', ['commercial_shoot', 'product_photography', 'event_coverage', 'client_project', 'training', 'maintenance', 'other'])->nullable()->after('employee_phone');
            $table->string('custom_exit_purpose')->nullable()->after('exit_purpose');
            $table->string('client_entity_name')->nullable()->after('custom_exit_purpose');
            $table->text('shoot_location')->nullable()->after('client_entity_name');
            $table->dateTime('exit_duration_from')->nullable()->after('shoot_location');
            $table->dateTime('exit_duration_to')->nullable()->after('exit_duration_from');
            
            // Warehouse Manager
            $table->foreignId('warehouse_manager_id')->nullable()->constrained('users')->onDelete('set null')->after('direct_manager_id');
            
            // Return Tracking
            $table->enum('status', ['draft', 'submitted', 'dm_approved', 'dm_rejected', 'final_approved', 'final_rejected', 'returned'])->default('draft')->change();
            $table->date('return_date')->nullable()->after('rejection_reason');
            $table->string('return_supervisor_name')->nullable()->after('return_date');
            $table->string('return_supervisor_phone')->nullable()->after('return_supervisor_name');
            $table->text('equipment_condition_on_return')->nullable()->after('return_supervisor_phone');
            $table->text('supervisor_notes')->nullable()->after('equipment_condition_on_return');
            $table->string('returned_by_employee')->nullable()->after('supervisor_notes');
            
            // Add indexes for performance
            $table->index('warehouse_manager_id');
            $table->index('created_at');
            $table->index(['requester_id', 'status']);
            $table->index(['direct_manager_id', 'status']);
        });

        Schema::table('tbl_inventory_request_items', function (Blueprint $table) {
            // Exit tracking
            $table->string('serial_number')->nullable()->after('inventory_item_id');
            $table->text('condition_before_exit')->nullable()->after('expected_return_date');
            
            // Return tracking
            $table->integer('quantity_returned')->nullable()->after('condition_before_exit');
            $table->text('condition_after_return')->nullable()->after('quantity_returned');
            $table->text('return_notes')->nullable()->after('condition_after_return');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_inventory_request_items', function (Blueprint $table) {
            $table->dropColumn([
                'serial_number',
                'condition_before_exit',
                'quantity_returned',
                'condition_after_return',
                'return_notes',
            ]);
        });

        Schema::table('tbl_inventory_requests', function (Blueprint $table) {
            $table->dropIndex(['requester_id', 'status']);
            $table->dropIndex(['direct_manager_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['warehouse_manager_id']);
            
            $table->dropForeign(['warehouse_manager_id']);
            $table->dropColumn([
                'employee_name',
                'employee_position',
                'employee_phone',
                'exit_purpose',
                'custom_exit_purpose',
                'client_entity_name',
                'shoot_location',
                'exit_duration_from',
                'exit_duration_to',
                'warehouse_manager_id',
                'return_date',
                'return_supervisor_name',
                'return_supervisor_phone',
                'equipment_condition_on_return',
                'supervisor_notes',
                'returned_by_employee',
            ]);
        });
    }
};
