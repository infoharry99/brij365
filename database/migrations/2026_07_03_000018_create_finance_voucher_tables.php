<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voucher_number', 40)->unique();
            $table->string('voucher_type', 32)->index();
            $table->string('status', 24)->default('submitted')->index();
            $table->date('voucher_date')->index();
            $table->string('reference_number', 120)->nullable()->index();
            $table->text('narration');
            $table->string('currency', 3)->default('INR');
            $table->decimal('total_debit', 15, 2)->default(0);
            $table->decimal('total_credit', 15, 2)->default(0);
            $table->json('tax_summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->dateTime('rejected_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'voucher_date']);
            $table->index(['company_id', 'voucher_type']);
            $table->index(['project_id', 'voucher_date']);
        });

        Schema::create('financial_voucher_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('account_code', 64)->index();
            $table->string('account_name');
            $table->string('line_type', 16)->index();
            $table->decimal('amount', 15, 2);
            $table->string('party_type')->nullable()->index();
            $table->unsignedBigInteger('party_id')->nullable()->index();
            $table->string('cost_center', 120)->nullable()->index();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['financial_voucher_id', 'line_number']);
            $table->index(['party_type', 'party_id']);
            $table->index(['project_id', 'account_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_voucher_lines');
        Schema::dropIfExists('financial_vouchers');
    }
};
