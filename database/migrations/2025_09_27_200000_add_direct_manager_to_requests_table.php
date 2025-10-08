<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreignId('direct_manager_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['direct_manager_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropIndex(['direct_manager_id', 'state']);
            $table->dropConstrainedForeignId('direct_manager_id');
        });
    }
};
