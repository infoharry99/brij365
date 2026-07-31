<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('source');
            $table->string('entry_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();
            $table->date('document_date')->index();
            $table->string('document_number', 80)->index();
            $table->string('party_name');
            $table->string('party_gstin', 20)->nullable()->index();
            $table->string('place_of_supply_state', 4)->index();
            $table->string('transaction_type')->index();
            $table->string('hsn_sac', 20)->nullable()->index();
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->decimal('taxable_amount', 16, 2)->default(0);
            $table->decimal('cgst_amount', 16, 2)->default(0);
            $table->decimal('sgst_amount', 16, 2)->default(0);
            $table->decimal('igst_amount', 16, 2)->default(0);
            $table->decimal('cess_amount', 16, 2)->default(0);
            $table->decimal('total_tax_amount', 16, 2)->default(0);
            $table->string('status')->default('submitted')->index();
            $table->json('metadata')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document_number', 'transaction_type'], 'gst_entry_document_unique');
            $table->index(['company_id', 'period_year', 'period_month', 'status'], 'gst_entries_period_index');
        });

        Schema::create('gst_return_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_number', 40)->unique();
            $table->unsignedSmallInteger('period_year')->index();
            $table->unsignedTinyInteger('period_month')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->string('status')->default('prepared')->index();
            $table->unsignedInteger('entry_count')->default(0);
            $table->decimal('output_taxable_total', 16, 2)->default(0);
            $table->decimal('output_tax_total', 16, 2)->default(0);
            $table->decimal('input_taxable_total', 16, 2)->default(0);
            $table->decimal('input_tax_credit_total', 16, 2)->default(0);
            $table->decimal('net_tax_payable', 16, 2)->default(0);
            $table->json('summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->dateTime('locked_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'period_year', 'period_month'], 'gst_return_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_return_periods');
        Schema::dropIfExists('gst_entries');
    }
};
