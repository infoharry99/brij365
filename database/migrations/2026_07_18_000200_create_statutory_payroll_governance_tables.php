<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->decimal('payable_days', 8, 2)->unsigned()->default(0)->change();
        });

        Schema::create('statutory_rule_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('system_setting_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verified_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('configuration_checksum', 64);
            $table->text('attestation');
            $table->timestamp('verified_at');
            $table->timestamps();

            $table->index(['company_id', 'verified_at'], 'statutory_verification_company_date_index');
        });

        Schema::create('payroll_calculation_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('payroll_attendance_snapshot_id')->nullable();
            $table->foreign(
                'payroll_attendance_snapshot_id',
                'payroll_calc_snap_attendance_fk',
            )->references('id')->on('payroll_attendance_snapshots')->nullOnDelete();
            $table->foreignId('salary_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('INR');
            $table->unsignedSmallInteger('calculation_version')->default(1);
            $table->unsignedBigInteger('gross_minor')->default(0);
            $table->unsignedBigInteger('deduction_minor')->default(0);
            $table->unsignedBigInteger('employer_contribution_minor')->default(0);
            $table->unsignedBigInteger('net_minor')->default(0);
            $table->string('input_hash', 64);
            $table->string('result_hash', 64);
            $table->json('rule_context');
            $table->json('calculation_trace');
            $table->timestamps();

            $table->index(['company_id', 'employee_id', 'created_at'], 'payroll_snapshot_employee_index');
        });

        Schema::create('payroll_calculation_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_calculation_snapshot_id');
            $table->foreign(
                'payroll_calculation_snapshot_id',
                'payroll_calc_line_snapshot_fk',
            )->references('id')->on('payroll_calculation_snapshots')->cascadeOnDelete();
            $table->foreignId('system_setting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_code', 64);
            $table->string('component_name');
            $table->string('line_type', 32)->index();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->unsignedBigInteger('basis_minor')->default(0);
            $table->unsignedInteger('rate_ppm')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('trace')->nullable();
            $table->timestamps();

            $table->index(['payroll_calculation_snapshot_id', 'sort_order'], 'payroll_calculation_line_order_index');
        });
    }

    public function down(): void
    {
        $incompatiblePayableDays = DB::table('payroll_run_items')
            ->select(['id', 'payable_days'])
            ->orderBy('id')
            ->lazyById()
            ->contains(function (object $item): bool {
                $value = (float) $item->payable_days;

                return $value < 0 || $value > 65535 || floor($value) !== $value;
            });

        if ($incompatiblePayableDays) {
            throw new RuntimeException(
                'Cannot roll back governed payroll while payroll items contain fractional or out-of-range payable days.',
            );
        }

        Schema::dropIfExists('payroll_calculation_lines');
        Schema::dropIfExists('payroll_calculation_snapshots');
        Schema::dropIfExists('statutory_rule_verifications');

        Schema::table('payroll_run_items', function (Blueprint $table): void {
            $table->unsignedSmallInteger('payable_days')->default(0)->change();
        });
    }
};
