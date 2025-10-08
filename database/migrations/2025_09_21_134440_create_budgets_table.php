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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->year('fiscal_year');
            $table->decimal('cap', 15, 2);
            $table->decimal('consumed', 15, 2)->default(0);
            $table->decimal('remaining', 15, 2)->storedAs('cap - consumed');
            $table->decimal('utilization_percentage', 5, 2)->storedAs('(consumed / cap) * 100');
            $table->timestamps();

            $table->unique(['team_id', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
