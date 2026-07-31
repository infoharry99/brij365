<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('component_type')->index();
            $table->string('calculation_type')->default('fixed');
            $table->boolean('is_taxable')->default(true)->index();
            $table->boolean('is_statutory')->default(false)->index();
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->decimal('monthly_ctc', 14, 2);
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code', 'version']);
        });

        Schema::create('salary_structure_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('percentage_of_ctc', 6, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->unique(['salary_structure_id', 'payroll_component_id'], 'salary_structure_component_unique');
        });

        Schema::create('salary_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_structure_id')->constrained()->restrictOnDelete();
            $table->date('effective_from')->index();
            $table->date('effective_to')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'effective_from', 'effective_to'], 'salary_assignment_effective_index');
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('run_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->unsignedSmallInteger('working_days')->default(0);
            $table->string('status')->default('draft')->index();
            $table->decimal('gross_earnings', 16, 2)->default(0);
            $table->decimal('total_deductions', 16, 2)->default(0);
            $table->decimal('net_payable', 16, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'period_year', 'period_month']);
        });

        Schema::create('payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('monthly_ctc', 14, 2)->default(0);
            $table->unsignedSmallInteger('payable_days')->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('net_payable', 14, 2)->default(0);
            $table->json('component_breakup')->nullable();
            $table->string('status')->default('generated')->index();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('salary_assignments');
        Schema::dropIfExists('salary_structure_components');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('payroll_components');
    }
};
