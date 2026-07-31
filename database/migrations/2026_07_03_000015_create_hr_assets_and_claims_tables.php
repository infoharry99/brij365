<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recovered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('asset_code', 40)->unique();
            $table->string('category')->index();
            $table->string('name');
            $table->string('serial_number')->nullable()->index();
            $table->string('status')->default('available')->index();
            $table->string('condition')->default('good')->index();
            $table->date('assigned_on')->nullable()->index();
            $table->date('recovered_on')->nullable()->index();
            $table->decimal('estimated_value', 14, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->json('workflow_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('claim_number', 40)->unique();
            $table->string('claim_type')->index();
            $table->string('status')->default('submitted')->index();
            $table->date('claim_date')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');
            $table->string('description');
            $table->json('attachments')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['claim_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('employee_assets');
    }
};
