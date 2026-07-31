<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_separation_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable();
            $table->foreignId('hr_approved_by_user_id')->nullable();
            $table->foreignId('finance_approved_by_user_id')->nullable();
            $table->foreignId('completed_by_user_id')->nullable();
            $table->string('settlement_number', 40)->unique();
            $table->string('separation_type')->index();
            $table->string('status')->default('initiated')->index();
            $table->date('resignation_date')->nullable()->index();
            $table->date('last_working_date')->index();
            $table->date('settlement_date')->nullable()->index();
            $table->text('reason')->nullable();
            $table->json('calculation_breakdown')->nullable();
            $table->json('clearance_blockers')->nullable();
            $table->decimal('last_salary_amount', 14, 2)->default(0);
            $table->decimal('leave_encashment_amount', 14, 2)->default(0);
            $table->decimal('bonus_amount', 14, 2)->default(0);
            $table->decimal('gratuity_amount', 14, 2)->default(0);
            $table->decimal('claim_payable_amount', 14, 2)->default(0);
            $table->decimal('notice_recovery_amount', 14, 2)->default(0);
            $table->decimal('loan_recovery_amount', 14, 2)->default(0);
            $table->decimal('asset_recovery_amount', 14, 2)->default(0);
            $table->decimal('tax_recovery_amount', 14, 2)->default(0);
            $table->decimal('gross_payable', 14, 2)->default(0);
            $table->decimal('total_recoveries', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('hr_approved_at')->nullable()->index();
            $table->dateTime('finance_approved_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->foreign('initiated_by_user_id', 'fnf_initiated_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('hr_approved_by_user_id', 'fnf_hr_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('finance_approved_by_user_id', 'fnf_finance_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by_user_id', 'fnf_completed_by_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_separation_settlements');
    }
};
