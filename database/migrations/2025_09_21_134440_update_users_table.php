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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['USER', 'DIRECT_MANAGER', 'ACCOUNTANT', 'FINAL_MANAGER', 'ADMIN', 'SALES_REP', 'SUPER_ADMIN'])->default('USER');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_preference')->default('en');
            $table->string('timezone')->default('UTC');
            $table->string('date_format')->default('Y-m-d');
            $table->string('currency')->default('USD');
            $table->timestamp('last_login_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'team_id', 'department_id', 'status', 'avatar', 'phone',
                'position', 'first_name', 'last_name', 'language_preference',
                'timezone', 'date_format', 'currency', 'last_login_at'
            ]);
        });
    }
};
