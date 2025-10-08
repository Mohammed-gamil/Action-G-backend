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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->enum('type', ['IN', 'OUT', 'RESERVE', 'RELEASE', 'ADJUSTMENT', 'MAINTENANCE']); // Transaction types
            $table->integer('quantity'); // Positive for IN, negative for OUT
            $table->integer('quantity_before'); // Quantity before transaction
            $table->integer('quantity_after'); // Quantity after transaction
            $table->foreignId('related_request_id')->nullable()->constrained('requests')->onDelete('set null'); // Link to project request
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Who performed the action
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'type']);
            $table->index(['related_request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
