<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_overnight')->default(false);
            $table->unsignedSmallInteger('late_grace_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_grace_minutes')->default(0);
            $table->unsignedSmallInteger('half_day_threshold_minutes')->default(240);
            $table->unsignedSmallInteger('full_day_threshold_minutes')->default(480);
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['employee_id', 'effective_from', 'effective_to'], 'employee_shift_effective_index');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date')->index();
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            $table->string('source')->default('manual')->index();
            $table->string('status')->default('present')->index();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('worked_minutes')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['company_id', 'work_date']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('attendance_regularization_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_record_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_number', 40)->unique();
            $table->string('status')->default('submitted')->index();
            $table->date('work_date')->index();
            $table->dateTime('requested_check_in_at');
            $table->dateTime('requested_check_out_at');
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('decided_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['company_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_regularization_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employee_shift_assignments');
        Schema::dropIfExists('attendance_shifts');
    }
};
