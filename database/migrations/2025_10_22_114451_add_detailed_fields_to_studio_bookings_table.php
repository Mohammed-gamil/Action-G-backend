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
        Schema::table('tbl_studio_bookings', function (Blueprint $table) {
            // Client Contact Information
            $table->string('client_phone')->nullable()->after('client_name');
            $table->string('client_email')->nullable()->after('client_phone');
            
            // Business Information
            $table->string('business_name')->nullable()->after('client_email');
            $table->string('business_type')->nullable()->after('business_name'); // تجميل، مجوهرات، مطاعم، etc.
            
            // Custom project type
            $table->string('custom_project_type')->nullable()->after('project_type');
            
            // Duration in hours
            $table->decimal('duration_hours', 5, 2)->nullable()->after('end_time');
            
            // Time preference
            $table->enum('time_preference', ['morning', 'evening', 'flexible'])->nullable()->after('duration_hours');
            
            // Additional services needed
            $table->json('additional_services')->nullable()->after('equipment_needed'); 
            // Will store: lighting, makeup, decoration, catering
            
            // Client agreement
            $table->boolean('client_agreed')->default(false)->after('additional_services');
            $table->timestamp('agreement_date')->nullable()->after('client_agreed');
            
            // Notes/Special requests
            $table->text('special_notes')->nullable()->after('agreement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbl_studio_bookings', function (Blueprint $table) {
            $table->dropColumn([
                'client_phone',
                'client_email',
                'business_name',
                'business_type',
                'custom_project_type',
                'duration_hours',
                'time_preference',
                'additional_services',
                'client_agreed',
                'agreement_date',
                'special_notes'
            ]);
        });
    }
};
