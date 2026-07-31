<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_bank_transfer_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_number', 40)->unique();
            $table->string('bank_name')->index();
            $table->date('payment_date')->index();
            $table->string('status')->default('prepared')->index();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('control_total', 16, 2)->default(0);
            $table->string('checksum', 128);
            $table->text('csv_payload');
            $table->json('validation_summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('prepared_at')->nullable()->index();
            $table->dateTime('released_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_run_id', 'bank_name', 'payment_date'], 'payroll_bank_batch_run_bank_date_unique');
            $table->index(['company_id', 'status']);
        });

        Schema::create('payroll_bank_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_bank_transfer_batch_id');
            $table->foreignId('payroll_run_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('employee_code', 40);
            $table->string('beneficiary_name');
            $table->text('bank_account_number_encrypted');
            $table->string('bank_account_last4', 8)->index();
            $table->string('ifsc_code', 16)->index();
            $table->decimal('amount', 14, 2);
            $table->string('status')->default('prepared')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['payroll_bank_transfer_batch_id', 'payroll_run_item_id'], 'payroll_bank_batch_item_unique');
            $table->index(['employee_id', 'status']);
            $table->foreign('payroll_bank_transfer_batch_id', 'payroll_bank_item_batch_fk')
                ->references('id')
                ->on('payroll_bank_transfer_batches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_bank_transfer_items');
        Schema::dropIfExists('payroll_bank_transfer_batches');
    }
};
