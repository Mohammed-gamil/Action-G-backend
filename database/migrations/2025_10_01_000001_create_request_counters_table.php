<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_counters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // purchase|project
            $table->unsignedInteger('year');
            $table->unsignedInteger('seq')->default(0);
            $table->unique(['type', 'year'], 'request_counters_type_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_counters');
    }
};
