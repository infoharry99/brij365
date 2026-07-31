<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qualified_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('qualification_number', 40)->unique();
            $table->string('status', 32)->default('qualified')->index();
            $table->unsignedTinyInteger('score')->index();
            $table->unsignedTinyInteger('budget_score')->default(0);
            $table->unsignedTinyInteger('authority_score')->default(0);
            $table->unsignedTinyInteger('need_score')->default(0);
            $table->unsignedTinyInteger('timeline_score')->default(0);
            $table->string('preferred_configuration', 80)->nullable();
            $table->decimal('verified_budget_min', 14, 2)->nullable();
            $table->decimal('verified_budget_max', 14, 2)->nullable();
            $table->date('expected_booking_date')->nullable()->index();
            $table->text('decision_notes');
            $table->json('requirements')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('qualified_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['lead_id', 'status']);
        });

        Schema::create('site_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('scheduled_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visit_number', 40)->unique();
            $table->string('status', 32)->default('scheduled')->index();
            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('visit_mode', 32)->default('site')->index();
            $table->string('meeting_location')->nullable();
            $table->string('meeting_url', 1024)->nullable();
            $table->text('agenda')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->string('outcome', 64)->nullable()->index();
            $table->dateTime('completed_at')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable()->index();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->json('attendees')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'scheduled_at']);
            $table->index(['lead_id', 'scheduled_at']);
            $table->index(['assigned_to_user_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('lead_qualifications');
    }
};
