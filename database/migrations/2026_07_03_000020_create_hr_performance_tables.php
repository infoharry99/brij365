<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cycle_code', 40)->unique();
            $table->string('name');
            $table->string('frequency')->index();
            $table->string('status')->default('draft')->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->date('review_due_on')->nullable()->index();
            $table->string('department')->nullable()->index();
            $table->unsignedTinyInteger('rating_scale_min')->default(1);
            $table->unsignedTinyInteger('rating_scale_max')->default(5);
            $table->decimal('passing_score', 5, 2)->default(3);
            $table->json('rules')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'frequency', 'status']);
            $table->index(['company_id', 'starts_on', 'ends_on']);
        });

        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('self_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_number', 40)->unique();
            $table->string('status')->default('draft')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->dateTime('self_submitted_at')->nullable();
            $table->dateTime('manager_submitted_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->json('kpis')->nullable();
            $table->json('kra_summary')->nullable();
            $table->decimal('self_score', 5, 2)->nullable();
            $table->decimal('manager_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('final_rating')->nullable()->index();
            $table->text('strengths')->nullable();
            $table->text('improvement_areas')->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->boolean('pip_required')->default(false)->index();
            $table->string('pip_status')->nullable()->index();
            $table->json('pip_plan')->nullable();
            $table->json('workflow_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['performance_cycle_id', 'employee_id']);
            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['manager_employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_cycles');
    }
};
