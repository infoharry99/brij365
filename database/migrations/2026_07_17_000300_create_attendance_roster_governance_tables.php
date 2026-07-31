<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_shift_assignments', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::create('attendance_rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('status_note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'period_start', 'period_end'], 'attendance_roster_period_index');
        });

        Schema::create('attendance_rotation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('anchor_date')->index();
            $table->unsignedSmallInteger('cycle_days');
            $table->json('pattern');
            $table->unsignedSmallInteger('generation_horizon_days')->default(90);
            $table->string('status', 24)->default('active')->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'status'], 'attendance_rotation_employee_index');
        });

        Schema::create('attendance_roster_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attendance_rotation_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date')->index();
            $table->string('entry_type', 24)->default('shift');
            $table->string('source', 24)->default('manual');
            $table->string('occurrence_key', 96)->unique();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['attendance_roster_id', 'employee_id', 'work_date'],
                'attendance_roster_employee_date_unique',
            );
            $table->index(['employee_id', 'work_date'], 'attendance_roster_employee_date_index');
            $table->index(['attendance_roster_id', 'work_date'], 'attendance_roster_entry_date_index');
        });

        Schema::create('attendance_shift_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('requester_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('source_roster_entry_id')->constrained('attendance_roster_entries')->restrictOnDelete();
            $table->foreignId('target_roster_entry_id')->constrained('attendance_roster_entries')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_number', 40)->unique();
            $table->string('status', 24)->default('submitted')->index();
            $table->text('reason');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'status'], 'attendance_swap_company_status_index');
        });

        Schema::create('attendance_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 24)->default('finalized')->index();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->string('source_hash', 64);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'period_start', 'period_end', 'version'], 'attendance_period_company_version_unique');
            $table->index(['company_id', 'period_start', 'period_end', 'status'], 'attendance_period_status_index');
        });

        Schema::create('payroll_attendance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_period_lock_id')->constrained()->restrictOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('scheduled_days')->default(0);
            $table->unsignedSmallInteger('present_days')->default(0);
            $table->unsignedSmallInteger('paid_leave_days')->default(0);
            $table->unsignedSmallInteger('unpaid_days')->default(0);
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->decimal('payable_days', 8, 2)->unsigned()->default(0);
            $table->string('source_hash', 64);
            $table->json('calculation_trace')->nullable();
            $table->timestamps();

            $table->unique(['attendance_period_lock_id', 'employee_id'], 'payroll_attendance_lock_employee_unique');
            $table->index(['company_id', 'period_start', 'period_end'], 'payroll_attendance_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_attendance_snapshots');
        Schema::dropIfExists('attendance_period_locks');
        Schema::dropIfExists('attendance_shift_swap_requests');
        Schema::dropIfExists('attendance_roster_entries');
        Schema::dropIfExists('attendance_rotation_rules');
        Schema::dropIfExists('attendance_rosters');

        Schema::table('employee_shift_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }
};
