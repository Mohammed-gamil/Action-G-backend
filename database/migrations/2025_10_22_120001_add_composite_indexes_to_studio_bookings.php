<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbl_studio_bookings', function (Blueprint $table) {
            // Add composite indexes for better query performance
            $table->index(['requester_id', 'status'], 'idx_studio_requester_status');
            $table->index(['direct_manager_id', 'status'], 'idx_studio_manager_status');
            $table->index('created_at', 'idx_studio_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('tbl_studio_bookings', function (Blueprint $table) {
            $table->dropIndex('idx_studio_requester_status');
            $table->dropIndex('idx_studio_manager_status');
            $table->dropIndex('idx_studio_created_at');
        });
    }
};
