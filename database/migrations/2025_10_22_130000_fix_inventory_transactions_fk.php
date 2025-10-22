<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some environments/migration runs created the foreign key pointing to `requests`
        // but the inventory requests table is `tbl_inventory_requests`.
        if (!Schema::hasTable('inventory_transactions')) {
            return;
        }

        Schema::table('inventory_transactions', function (Blueprint $table) {
            // If the column doesn't exist we have nothing to do
            if (!Schema::hasColumn('inventory_transactions', 'related_request_id')) {
                return;
            }

            // Drop existing foreign if present. Use array form which is resilient to different constraint names.
            try {
                $table->dropForeign(['related_request_id']);
            } catch (\Exception $e) {
                // Ignore if it doesn't exist or cannot be dropped (we'll attempt to re-create below)
            }

            // Make sure column is nullable and then add correct FK to tbl_inventory_requests
            $table->unsignedBigInteger('related_request_id')->nullable()->change();

            try {
                $table->foreign('related_request_id')
                    ->references('id')
                    ->on('tbl_inventory_requests')
                    ->onDelete('set null');
            } catch (\Exception $e) {
                // If adding the foreign fails (missing table etc.) we'll leave column as-is.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inventory_transactions')) {
            return;
        }

        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'related_request_id')) {
                return;
            }

            try {
                $table->dropForeign(['related_request_id']);
            } catch (\Exception $e) {
                // ignore
            }

            // Restore previous FK back to requests if that table exists
            try {
                $table->foreign('related_request_id')
                    ->references('id')
                    ->on('requests')
                    ->onDelete('set null');
            } catch (\Exception $e) {
                // ignore
            }
        });
    }
};
