<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_exit_interviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_separation_settlement_id')->nullable();
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('interview_number', 40)->unique();
            $table->string('status')->default('scheduled')->index();
            $table->date('interview_due_on')->index();
            $table->dateTime('submitted_at')->nullable()->index();
            $table->dateTime('reviewed_at')->nullable()->index();
            $table->string('separation_reason')->nullable()->index();
            $table->string('rehire_recommendation')->nullable()->index();
            $table->unsignedTinyInteger('overall_experience_rating')->nullable();
            $table->unsignedTinyInteger('manager_relationship_rating')->nullable();
            $table->unsignedTinyInteger('workload_rating')->nullable();
            $table->unsignedTinyInteger('compensation_rating')->nullable();
            $table->text('public_feedback')->nullable();
            $table->text('improvement_suggestions')->nullable();
            $table->longText('confidential_responses')->nullable();
            $table->json('risk_flags')->nullable();
            $table->json('questionnaire_template')->nullable();
            $table->text('hr_review_notes')->nullable();
            $table->json('action_items')->nullable();
            $table->json('workflow_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['company_id', 'separation_reason']);
            $table->foreign('employee_separation_settlement_id', 'exit_interview_settlement_fk')
                ->references('id')
                ->on('employee_separation_settlements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_exit_interviews');
    }
};
