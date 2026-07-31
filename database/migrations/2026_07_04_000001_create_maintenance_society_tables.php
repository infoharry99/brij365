<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('society_formations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('formation_number', 32)->unique();
            $table->string('society_name');
            $table->string('association_type', 40)->default('cooperative_society')->index();
            $table->unsignedInteger('total_units')->default(0);
            $table->unsignedInteger('occupied_units')->default(0);
            $table->string('registration_number')->nullable();
            $table->date('application_filed_on')->nullable();
            $table->date('registered_on')->nullable();
            $table->date('target_handover_on')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('current_stage', 120)->nullable();
            $table->string('next_step')->nullable();
            $table->json('committee_members')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'society_name']);
            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('common_area_handover_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('society_formation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('signed_off_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('item_number', 32)->unique();
            $table->string('facility_name');
            $table->string('category', 60)->default('common_area')->index();
            $table->unsignedInteger('checklist_total')->default(0);
            $table->unsignedInteger('checklist_completed')->default(0);
            $table->string('status', 32)->default('pending')->index();
            $table->date('target_completion_on')->nullable();
            $table->date('signed_off_on')->nullable();
            $table->json('snag_summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['society_formation_id', 'status']);
        });

        Schema::create('maintenance_dues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('due_number', 32)->unique();
            $table->date('period_start_on')->index();
            $table->date('period_end_on')->index();
            $table->date('due_on')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_amount', 14, 2)->default(0);
            $table->string('status', 32)->default('due')->index();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->dateTime('last_reminded_at')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_unit_id', 'period_start_on', 'period_end_on'], 'maintenance_due_period_unique');
            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_dues');
        Schema::dropIfExists('common_area_handover_items');
        Schema::dropIfExists('society_formations');
    }
};
