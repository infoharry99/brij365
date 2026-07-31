<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('contractor_measurement_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('bill_number', 40)->unique();
            $table->date('bill_date')->index();
            $table->string('status')->default('submitted')->index();
            $table->decimal('gross_amount', 16, 2)->default(0);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 16, 2)->default(0);
            $table->decimal('deduction_amount', 16, 2)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('payable_amount', 16, 2)->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->decimal('balance_amount', 16, 2)->default(0);
            $table->json('deductions')->nullable();
            $table->json('payment_history')->nullable();
            $table->json('workflow_history')->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'vendor_id', 'status'], 'contractor_bills_scope_index');
            $table->index(['company_id', 'bill_date', 'status'], 'contractor_bills_date_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_bills');
    }
};
