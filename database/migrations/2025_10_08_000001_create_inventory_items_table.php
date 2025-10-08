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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // Unique identifier like INV-001
            $table->text('description')->nullable();
            $table->string('category'); // e.g., Tools, Equipment, Materials
            $table->integer('quantity')->default(0); // Available quantity
            $table->integer('reserved_quantity')->default(0); // Reserved for projects
            $table->string('unit')->default('piece'); // Unit of measurement (piece, kg, meter, etc.)
            $table->decimal('unit_cost', 15, 2)->nullable(); // Cost per unit
            $table->string('location')->nullable(); // Storage location
            $table->string('condition')->default('good'); // good, fair, needs_maintenance
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('added_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes(); // Soft delete for history

            $table->index(['category', 'is_active']);
            $table->index(['code']);
            $table->index(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
