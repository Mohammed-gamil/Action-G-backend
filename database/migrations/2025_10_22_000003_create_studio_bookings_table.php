<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_studio_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('project_type', ['photography', 'videography', 'both']);
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('direct_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->json('equipment_needed')->nullable();
            $table->integer('crew_size')->nullable();
            $table->string('client_name')->nullable();
            $table->enum('status', ['draft', 'submitted', 'dm_approved', 'dm_rejected', 'final_approved', 'final_rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->index('requester_id');
            $table->index('direct_manager_id');
            $table->index('status');
            $table->index('booking_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_studio_bookings');
    }
};
