<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add email to clients table if it doesn't exist
        Schema::table('tbl_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('tbl_clients', 'email')) {
                $table->string('email', 100)->nullable()->after('contact_person');
            }
        });

        // Simplify visits table - remove complex fields and add new simplified fields
        Schema::table('tbl_visits', function (Blueprint $table) {
            // Add new simplified fields if they don't exist
            if (!Schema::hasColumn('tbl_visits', 'visit_type')) {
                $table->string('visit_type', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('tbl_visits', 'visit_result')) {
                $table->string('visit_result', 50)->nullable()->after('visit_type');
            }
            if (!Schema::hasColumn('tbl_visits', 'visit_reason')) {
                $table->string('visit_reason', 50)->nullable()->after('visit_result');
            }
            if (!Schema::hasColumn('tbl_visits', 'follow_up_date')) {
                $table->date('follow_up_date')->nullable()->after('visit_reason');
            }
            if (!Schema::hasColumn('tbl_visits', 'location_lat')) {
                $table->decimal('location_lat', 10, 8)->nullable()->after('follow_up_date');
            }
            if (!Schema::hasColumn('tbl_visits', 'location_lng')) {
                $table->decimal('location_lng', 11, 8)->nullable()->after('location_lat');
            }
        });
        
        // Drop complex fields only if they exist
        Schema::table('tbl_visits', function (Blueprint $table) {
            // Drop foreign keys first if they exist
            if (Schema::hasColumn('tbl_visits', 'product_category_id')) {
                $table->dropForeign(['product_category_id']);
            }
            if (Schema::hasColumn('tbl_visits', 'approved_by_admin_id')) {
                $table->dropForeign(['approved_by_admin_id']);
            }
        });
        
        Schema::table('tbl_visits', function (Blueprint $table) {
            $columnsToDelete = [
                'has_previous_agency',
                'previous_agency_name',
                'needs_voiceover',
                'voiceover_language',
                'shooting_goals',
                'shooting_goals_other_text',
                'service_types',
                'service_types_other_text',
                'preferred_location',
                'product_category_id',
                'product_description',
                'estimated_product_count',
                'preferred_shoot_date',
                'budget_range',
                'action_required_message',
                'approved_at',
                'approved_by_admin_id',
            ];
            
            $existingColumns = [];
            foreach ($columnsToDelete as $column) {
                if (Schema::hasColumn('tbl_visits', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });

        // First, temporarily change status to VARCHAR to update values
        DB::statement("ALTER TABLE tbl_visits MODIFY COLUMN status VARCHAR(50) DEFAULT 'draft'");
        
        // Update existing status values to match new enum
        DB::statement("UPDATE tbl_visits SET status = CASE 
            WHEN status IN ('approved', 'quotation_sent', 'closed_won') THEN 'completed'
            WHEN status IN ('pending_review', 'action_required') THEN 'submitted'
            WHEN status = 'closed_lost' THEN 'completed'
            ELSE 'draft'
        END
        WHERE status NOT IN ('draft', 'submitted', 'completed')");
        
        // Now change it back to enum with new values
        DB::statement("ALTER TABLE tbl_visits MODIFY COLUMN status ENUM('draft', 'submitted', 'completed') DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore complex fields to visits table
        Schema::table('tbl_visits', function (Blueprint $table) {
            // Remove simplified fields
            $table->dropColumn([
                'visit_type',
                'visit_result',
                'visit_reason',
                'follow_up_date',
                'location_lat',
                'location_lng',
            ]);
            
            // Add back complex fields
            $table->boolean('has_previous_agency')->default(false);
            $table->string('previous_agency_name', 200)->nullable();
            $table->boolean('needs_voiceover')->default(false);
            $table->string('voiceover_language', 50)->nullable();
            $table->json('shooting_goals')->nullable();
            $table->text('shooting_goals_other_text')->nullable();
            $table->json('service_types')->nullable();
            $table->text('service_types_other_text')->nullable();
            $table->enum('preferred_location', ['client_location', 'action_studio', 'external'])->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained('tbl_product_categories')->onDelete('set null');
            $table->text('product_description')->nullable();
            $table->integer('estimated_product_count')->nullable();
            $table->date('preferred_shoot_date')->nullable();
            $table->string('budget_range', 100)->nullable();
            $table->text('action_required_message')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
        });

        // Restore original status enum
        DB::statement("ALTER TABLE tbl_visits MODIFY COLUMN status ENUM('draft', 'submitted', 'pending_review', 'action_required', 'approved', 'quotation_sent', 'closed_won', 'closed_lost') DEFAULT 'draft'");

        // Remove email from clients table
        Schema::table('tbl_clients', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
