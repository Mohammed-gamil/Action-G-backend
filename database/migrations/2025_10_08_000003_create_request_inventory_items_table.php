<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('request_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->integer('quantity_requested'); // How many items requested
            $table->integer('quantity_allocated')->default(0); // How many actually allocated
            $table->enum('status', ['PENDING', 'RESERVED', 'ALLOCATED', 'RETURNED', 'LOST'])->default('PENDING');
            $table->date('expected_return_date')->nullable();
            $table->date('actual_return_date')->nullable();
            $table->text('return_notes')->nullable();
            $table->timestamps();

            $table->unique(['request_id', 'inventory_item_id']);
            $table->index(['inventory_item_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_inventory_items');
    }
};
