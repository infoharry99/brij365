<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_processing_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->string('processing_type')->index();
            $table->string('status')->default('preview')->index();
            $table->boolean('is_dry_run')->default(true)->index();
            $table->json('rules_snapshot')->nullable();
            $table->json('summary')->nullable();
            $table->json('line_items')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('posted_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'period_year', 'processing_type', 'status'], 'leave_processing_status_unique');
            $table->index(['company_id', 'period_year']);
        });

        Schema::create('leave_encashments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payroll_marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('encashment_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->string('status')->default('submitted')->index();
            $table->decimal('requested_days', 8, 2);
            $table->decimal('approved_days', 8, 2)->default(0);
            $table->decimal('daily_rate', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->text('request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('payroll_reference')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->dateTime('payroll_marked_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'period_year', 'status']);
            $table->index(['employee_id', 'period_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_encashments');
        Schema::dropIfExists('leave_processing_runs');
    }
};
