<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('construction_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('milestone_code', 40);
            $table->string('name');
            $table->string('phase')->index();
            $table->date('planned_start_on')->index();
            $table->date('planned_end_on')->index();
            $table->date('actual_start_on')->nullable()->index();
            $table->date('actual_end_on')->nullable()->index();
            $table->decimal('weight_percent', 6, 2)->default(0);
            $table->decimal('progress_percent', 6, 2)->default(0);
            $table->string('status')->default('planned')->index();
            $table->json('dependencies')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'milestone_code']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('daily_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_number', 40)->unique();
            $table->date('report_date')->index();
            $table->string('weather')->nullable();
            $table->unsignedSmallInteger('manpower_count')->default(0);
            $table->json('manpower_breakup')->nullable();
            $table->json('progress_items');
            $table->json('materials_used')->nullable();
            $table->json('equipment_used')->nullable();
            $table->text('work_summary');
            $table->text('safety_observations')->nullable();
            $table->text('quality_observations')->nullable();
            $table->text('blockers')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'report_date'], 'daily_progress_project_date_unique');
            $table->index(['company_id', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_progress_reports');
        Schema::dropIfExists('construction_milestones');
    }
};
