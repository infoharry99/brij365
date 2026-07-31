<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_confirmation_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('case_number', 40)->unique();
            $table->string('status')->default('due')->index();
            $table->date('probation_starts_on')->nullable()->index();
            $table->date('probation_ends_on')->index();
            $table->date('review_due_on')->index();
            $table->string('manager_recommendation')->nullable()->index();
            $table->text('manager_comments')->nullable();
            $table->json('review_scores')->nullable();
            $table->string('hr_decision')->nullable()->index();
            $table->text('hr_comments')->nullable();
            $table->date('confirmation_effective_on')->nullable()->index();
            $table->date('extended_until')->nullable()->index();
            $table->string('confirmation_letter_reference')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('manager_submitted_at')->nullable()->index();
            $table->dateTime('hr_decided_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'probation_ends_on'], 'employee_confirmation_probation_unique');
            $table->index(['company_id', 'status']);
            $table->index(['manager_employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_confirmation_cases');
    }
};
