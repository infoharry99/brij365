<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_tasks', 'lock_version')) {
            Schema::table('work_tasks', function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1)->after('client_token');
                $table->index(['company_id', 'status', 'priority', 'due_at'], 'work_tasks_scope_status_due_idx');
                $table->index(['company_id', 'assigned_to_user_id', 'status', 'due_at'], 'work_tasks_assignee_status_due_idx');
            });
        }

        // MySQL DDL is not transactional. A failed first attempt may leave only
        // these new, unregistered tables behind, so rebuilding them is safe.
        Schema::dropIfExists('work_task_saved_views');
        Schema::dropIfExists('work_task_reminder_deliveries');
        Schema::dropIfExists('work_task_recurrence_occurrences');
        Schema::dropIfExists('work_task_recurrence_rules');
        Schema::dropIfExists('work_task_completion_approvals');

        Schema::create('work_task_completion_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->text('request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'created_at'], 'task_completion_approval_queue_idx');
            $table->index(['work_task_id', 'status']);
        });

        Schema::create('work_task_recurrence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('root_work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('interval')->default(1);
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->dateTime('next_run_at')->nullable()->index();
            $table->dateTime('until_at')->nullable();
            $table->dateTime('last_generated_at')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('generation_count')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('root_work_task_id');
            $table->index(['company_id', 'status', 'next_run_at'], 'task_recurrence_due_idx');
        });

        Schema::create('work_task_recurrence_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('work_task_recurrence_rule_id');
            $table->foreignId('source_work_task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->foreignId('generated_work_task_id')->nullable()->constrained('work_tasks')->nullOnDelete();
            $table->dateTime('scheduled_for');
            $table->string('status', 16)->default('generated');
            $table->string('idempotency_key', 96)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['work_task_recurrence_rule_id', 'scheduled_for'], 'task_recurrence_schedule_unique');
            $table->foreign('work_task_recurrence_rule_id', 'task_recur_occ_rule_fk')->references('id')->on('work_task_recurrence_rules')->cascadeOnDelete();
        });

        Schema::create('work_task_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('reminder_at')->index();
            $table->unsignedInteger('minutes_before');
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('idempotency_key', 96)->unique();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'reminder_at'], 'task_reminder_delivery_due_idx');
        });

        Schema::create('work_task_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
            $table->index(['company_id', 'user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_saved_views');
        Schema::dropIfExists('work_task_reminder_deliveries');
        Schema::dropIfExists('work_task_recurrence_occurrences');
        Schema::dropIfExists('work_task_recurrence_rules');
        Schema::dropIfExists('work_task_completion_approvals');
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->dropIndex('work_tasks_scope_status_due_idx');
            $table->dropIndex('work_tasks_assignee_status_due_idx');
            $table->dropColumn('lock_version');
        });
    }
};
