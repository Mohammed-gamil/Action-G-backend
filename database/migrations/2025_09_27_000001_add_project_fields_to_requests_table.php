<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('location')->nullable()->after('category');
            $table->dateTime('start_time')->nullable()->after('needed_by_date');
            $table->dateTime('end_time')->nullable()->after('start_time');
            $table->dateTime('active_from')->nullable()->after('funds_transferred_at');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['location', 'start_time', 'end_time', 'active_from']);
        });
    }
};
