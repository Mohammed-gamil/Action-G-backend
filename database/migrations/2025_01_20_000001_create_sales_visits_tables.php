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
        // Business Types Table
        Schema::create('tbl_business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('is_active');
        });

        // Product Categories Table
        Schema::create('tbl_product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 100);
            $table->string('name_ar', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('is_active');
        });

        // Clients Table
        Schema::create('tbl_clients', function (Blueprint $table) {
            $table->id();
            $table->string('store_name', 200);
            $table->string('contact_person', 100);
            $table->string('mobile', 20);
            $table->string('mobile_2', 20)->nullable();
            $table->text('address');
            $table->foreignId('business_type_id')->constrained('tbl_business_types')->onDelete('restrict');
            $table->foreignId('created_by_rep_id')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            
            $table->index('store_name');
            $table->index('mobile');
            $table->index('created_by_rep_id');
        });

        // Visits Table
        Schema::create('tbl_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('tbl_clients')->onDelete('cascade');
            $table->foreignId('rep_id')->constrained('users')->onDelete('restrict');
            $table->date('visit_date');
            $table->enum('status', [
                'draft',
                'submitted',
                'pending_review',
                'action_required',
                'approved',
                'quotation_sent',
                'closed_won',
                'closed_lost'
            ])->default('draft');
            
            // Client Needs
            $table->boolean('has_previous_agency')->default(false);
            $table->string('previous_agency_name', 200)->nullable();
            $table->boolean('needs_voiceover')->default(false);
            $table->string('voiceover_language', 50)->nullable();
            $table->json('shooting_goals')->nullable(); // ['social_media', 'in_store', 'content_update', 'other']
            $table->text('shooting_goals_other_text')->nullable();
            $table->json('service_types')->nullable(); // ['product_photo', 'model_photo', 'video', 'other']
            $table->text('service_types_other_text')->nullable();
            $table->enum('preferred_location', ['client_location', 'action_studio', 'external'])->nullable();
            
            // Product Details
            $table->foreignId('product_category_id')->nullable()->constrained('tbl_product_categories')->onDelete('set null');
            $table->text('product_description')->nullable();
            $table->integer('estimated_product_count')->nullable();
            
            // Timing & Budget
            $table->date('preferred_shoot_date')->nullable();
            $table->string('budget_range', 100)->nullable();
            
            // Notes
            $table->text('rep_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('action_required_message')->nullable();
            
            // Metadata
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('rep_id');
            $table->index('status');
            $table->index('visit_date');
            $table->index('submitted_at');
        });

        // Visit Files Table
        Schema::create('tbl_visit_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('tbl_visits')->onDelete('cascade');
            $table->enum('file_type', ['photo', 'video']);
            $table->string('original_filename', 255);
            $table->string('stored_filename', 255);
            $table->bigInteger('file_size_bytes');
            $table->string('mime_type', 100);
            $table->string('storage_url', 500);
            $table->enum('upload_status', ['uploading', 'completed', 'failed'])->default('completed');
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->index('visit_id');
        });

        // Visit Status History Table
        Schema::create('tbl_visit_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('tbl_visits')->onDelete('cascade');
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50);
            $table->foreignId('changed_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            
            $table->index('visit_id');
            $table->index('changed_at');
        });

        // Seed default business types
        DB::table('tbl_business_types')->insert([
            ['name_en' => 'Retail Store', 'name_ar' => 'متجر تجزئة', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Restaurant', 'name_ar' => 'مطعم', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Cafe', 'name_ar' => 'مقهى', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Hotel', 'name_ar' => 'فندق', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Showroom', 'name_ar' => 'صالة عرض', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Mall', 'name_ar' => 'مول تجاري', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Factory', 'name_ar' => 'مصنع', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Warehouse', 'name_ar' => 'مستودع', 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Other', 'name_ar' => 'أخرى', 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed default product categories
        DB::table('tbl_product_categories')->insert([
            ['name_en' => 'Electronics', 'name_ar' => 'إلكترونيات', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Fashion & Apparel', 'name_ar' => 'أزياء وملابس', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Food & Beverage', 'name_ar' => 'أغذية ومشروبات', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Home & Furniture', 'name_ar' => 'منزل وأثاث', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Beauty & Cosmetics', 'name_ar' => 'تجميل ومستحضرات', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Automotive', 'name_ar' => 'سيارات', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Jewelry', 'name_ar' => 'مجوهرات', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Sports & Fitness', 'name_ar' => 'رياضة ولياقة', 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['name_en' => 'Other', 'name_ar' => 'أخرى', 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbl_visit_status_history');
        Schema::dropIfExists('tbl_visit_files');
        Schema::dropIfExists('tbl_visits');
        Schema::dropIfExists('tbl_clients');
        Schema::dropIfExists('tbl_product_categories');
        Schema::dropIfExists('tbl_business_types');
    }
};
