<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code', 40);
            $table->string('name');
            $table->string('rule_type')->index();
            $table->string('basis')->default('booking_value')->index();
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->decimal('fixed_amount', 14, 2)->default(0);
            $table->decimal('target_amount', 16, 2)->default(0);
            $table->json('slab_rules')->nullable();
            $table->json('eligibility_rules')->nullable();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'rule_code']);
            $table->index(['company_id', 'status', 'effective_from'], 'commission_rules_scope_index');
        });

        Schema::create('commission_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->string('status')->default('generated')->index();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('source_total', 16, 2)->default(0);
            $table->decimal('eligible_total', 16, 2)->default(0);
            $table->decimal('commission_total', 16, 2)->default(0);
            $table->json('calculation_summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'commission_rule_id', 'period_year', 'period_month'], 'commission_run_period_unique');
            $table->index(['company_id', 'status', 'period_year', 'period_month'], 'commission_runs_scope_index');
        });

        Schema::create('commission_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payroll_run_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();
            $table->decimal('source_amount', 16, 2)->default(0);
            $table->decimal('eligible_amount', 16, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->string('status')->default('generated')->index();
            $table->json('rule_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('payroll_included_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['commission_run_id', 'employee_id', 'booking_id'], 'commission_item_source_unique');
            $table->index(['company_id', 'employee_id', 'status', 'period_year', 'period_month'], 'commission_items_payroll_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_items');
        Schema::dropIfExists('commission_runs');
        Schema::dropIfExists('commission_rules');
    }
};
