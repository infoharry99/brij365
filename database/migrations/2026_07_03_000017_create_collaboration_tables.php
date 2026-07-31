<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('task_number', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('medium')->index();
            $table->string('status', 24)->default('open')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('module_context', 64)->nullable()->index();
            $table->string('related_type')->nullable()->index();
            $table->unsignedBigInteger('related_id')->nullable()->index();
            $table->json('checklist')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'assigned_to_user_id']);
            $table->index(['company_id', 'project_id']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('calendar_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organizer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_number', 32)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('event_type', 32)->default('meeting')->index();
            $table->string('status', 24)->default('scheduled')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('location')->nullable();
            $table->string('meeting_url', 1024)->nullable();
            $table->string('visibility', 24)->default('internal')->index();
            $table->json('attendees')->nullable();
            $table->json('reminders')->nullable();
            $table->string('related_type')->nullable()->index();
            $table->unsignedBigInteger('related_id')->nullable()->index();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'starts_at']);
            $table->index(['company_id', 'project_id']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('work_tasks');
    }
};
