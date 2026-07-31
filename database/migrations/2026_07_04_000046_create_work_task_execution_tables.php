<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_task_subtasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('status', 40)->default('open');
            $table->string('priority', 40)->default('medium');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'work_task_id']);
            $table->index(['company_id', 'assigned_to_user_id']);
            $table->index(['work_task_id', 'status']);
            $table->index(['due_at', 'status']);
        });

        Schema::create('work_task_time_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('logged_on');
            $table->unsignedInteger('minutes');
            $table->text('note')->nullable();
            $table->string('source', 80)->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'work_task_id']);
            $table->index(['company_id', 'user_id', 'logged_on']);
            $table->index(['work_task_id', 'logged_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_time_logs');
        Schema::dropIfExists('work_task_subtasks');
    }
};
