<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opening_code', 40)->unique();
            $table->string('title');
            $table->string('department')->index();
            $table->string('designation')->index();
            $table->unsignedSmallInteger('positions')->default(1);
            $table->string('employment_type')->default('full_time')->index();
            $table->string('work_location')->nullable();
            $table->decimal('budget_min_ctc', 14, 2)->nullable();
            $table->decimal('budget_max_ctc', 14, 2)->nullable();
            $table->string('status')->default('open')->index();
            $table->date('target_hiring_date')->nullable()->index();
            $table->json('required_skills')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'department', 'status']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_opening_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('candidate_code', 40)->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 30)->index();
            $table->string('source')->index();
            $table->string('current_company')->nullable();
            $table->decimal('experience_years', 5, 2)->default(0);
            $table->decimal('current_ctc', 14, 2)->nullable();
            $table->decimal('expected_ctc', 14, 2)->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->json('skills')->nullable();
            $table->json('documents')->nullable();
            $table->string('stage')->default('screening')->index();
            $table->string('status')->default('active')->index();
            $table->json('stage_history')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'email'], 'candidate_company_email_unique');
            $table->unique(['company_id', 'phone'], 'candidate_company_phone_unique');
            $table->index(['company_id', 'source', 'stage']);
        });

        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('interview_code', 40)->unique();
            $table->string('round_name');
            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->string('mode')->index();
            $table->string('venue_or_link')->nullable();
            $table->json('panel_user_ids');
            $table->string('status')->default('scheduled')->index();
            $table->json('feedback')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'scheduled_at', 'status']);
        });

        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('offer_number', 40)->unique();
            $table->string('template_code');
            $table->decimal('offered_ctc', 14, 2);
            $table->date('joining_date')->index();
            $table->json('placeholders');
            $table->string('status')->default('draft')->index();
            $table->dateTime('released_at')->nullable()->index();
            $table->json('document_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'joining_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interviews');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('job_openings');
    }
};
