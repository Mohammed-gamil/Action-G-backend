<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run on MySQL/MariaDB. Skip on SQLite/others where ALTER ... MODIFY ENUM is unsupported.
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'])) {
            return; // no-op
        }

        DB::statement("ALTER TABLE `requests` MODIFY `state` ENUM(
            'DRAFT','SUBMITTED','DM_APPROVED','DM_REJECTED',
            'ACCT_APPROVED','ACCT_REJECTED','FINAL_APPROVED','FINAL_REJECTED','FUNDS_TRANSFERRED',
            'PROCESSING','DONE','PAID'
        ) NOT NULL DEFAULT 'DRAFT'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'])) {
            return; // no-op
        }

        DB::statement("ALTER TABLE `requests` MODIFY `state` ENUM(
            'DRAFT','SUBMITTED','DM_APPROVED','DM_REJECTED',
            'ACCT_APPROVED','ACCT_REJECTED','FINAL_APPROVED','FINAL_REJECTED','FUNDS_TRANSFERRED'
        ) NOT NULL DEFAULT 'DRAFT'");
    }
};
