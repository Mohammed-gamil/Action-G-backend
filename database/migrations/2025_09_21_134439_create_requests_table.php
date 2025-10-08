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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id')->unique(); // PR-2024-001, PROJ-2024-001
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['purchase', 'project']);
            $table->string('category');
            $table->decimal('desired_cost', 15, 2);
            $table->string('currency', 3)->default('USD');
            $table->date('needed_by_date');
            $table->enum('state', [
                'DRAFT', 'SUBMITTED', 'DM_APPROVED', 'DM_REJECTED',
                'ACCT_APPROVED', 'ACCT_REJECTED', 'FINAL_APPROVED',
                'FINAL_REJECTED', 'FUNDS_TRANSFERRED'
            ])->default('DRAFT');
            $table->foreignId('current_approver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('payout_channel', ['WALLET', 'COMPANY', 'COURIER'])->nullable();
            $table->string('payout_reference')->nullable();
            $table->timestamp('funds_transferred_at')->nullable();

            // Project-specific fields
            $table->string('client_name')->nullable();
            $table->text('project_description')->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->decimal('total_benefit', 15, 2)->nullable();
            $table->decimal('total_price', 15, 2)->nullable();

            $table->timestamps();

            $table->index(['requester_id', 'state']);
            $table->index(['current_approver_id']);
            $table->index(['type', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
