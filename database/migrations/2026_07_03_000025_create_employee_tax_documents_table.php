<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tax_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number', 40)->unique();
            $table->string('document_type')->default('form_16')->index();
            $table->string('financial_year', 16)->index();
            $table->string('assessment_year', 16)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('generated')->index();
            $table->decimal('gross_salary', 16, 2)->default(0);
            $table->decimal('taxable_income', 16, 2)->default(0);
            $table->decimal('tds_deducted', 16, 2)->default(0);
            $table->decimal('net_salary_paid', 16, 2)->default(0);
            $table->json('payroll_run_ids')->nullable();
            $table->json('component_summary')->nullable();
            $table->json('tax_configuration_snapshot')->nullable();
            $table->longText('document_payload')->nullable();
            $table->string('issue_reference')->nullable();
            $table->text('employee_acknowledgement_note')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('generated_at')->nullable()->index();
            $table->dateTime('issued_at')->nullable()->index();
            $table->dateTime('acknowledged_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'employee_id', 'document_type', 'financial_year', 'version'], 'employee_tax_document_version_unique');
            $table->index(['company_id', 'financial_year', 'status']);
            $table->index(['employee_id', 'financial_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tax_documents');
    }
};
