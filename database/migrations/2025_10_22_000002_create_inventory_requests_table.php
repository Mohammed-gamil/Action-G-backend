<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('direct_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['draft', 'submitted', 'dm_approved', 'dm_rejected', 'final_approved', 'final_rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index('requester_id');
            $table->index('direct_manager_id');
            $table->index('status');
        });

        Schema::create('tbl_inventory_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_request_id')->constrained('tbl_inventory_requests')->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->timestamps();
            
            $table->index('inventory_request_id');
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_inventory_request_items');
        Schema::dropIfExists('tbl_inventory_requests');
    }
};
