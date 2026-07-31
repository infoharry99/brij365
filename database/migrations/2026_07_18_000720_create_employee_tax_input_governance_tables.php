<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tax_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('financial_year', 9);
            $table->string('regime_code', 64);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('input_payload');
            $table->string('input_checksum', 64);
            $table->json('workflow_history')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'employee_id', 'financial_year'], 'employee_tax_profile_year_unique');
            $table->index(['company_id', 'financial_year', 'status'], 'employee_tax_profile_company_year_index');
        });

        Schema::create('employee_tax_declarations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_tax_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('managed_document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_code', 64);
            $table->string('declaration_type', 32);
            $table->string('status', 24)->default('draft')->index();
            $table->text('amount_payload');
            $table->text('decision_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['employee_tax_profile_id', 'category_code'], 'employee_tax_declaration_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tax_declarations');
        Schema::dropIfExists('employee_tax_profiles');
    }
};
