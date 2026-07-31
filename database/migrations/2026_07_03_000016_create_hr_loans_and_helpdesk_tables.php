<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('loan_number', 40)->unique();
            $table->string('loan_type')->index();
            $table->string('status')->default('submitted')->index();
            $table->decimal('principal_amount', 14, 2);
            $table->decimal('approved_amount', 14, 2)->default(0);
            $table->unsignedSmallInteger('installment_months')->default(1);
            $table->decimal('monthly_installment', 14, 2)->default(0);
            $table->date('requested_on')->index();
            $table->date('repayment_starts_on')->nullable()->index();
            $table->string('purpose');
            $table->text('decision_note')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('disbursed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('hr_helpdesk_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 40)->unique();
            $table->string('category')->index();
            $table->string('priority')->default('medium')->index();
            $table->string('status')->default('open')->index();
            $table->string('subject');
            $table->text('description');
            $table->text('resolution_summary')->nullable();
            $table->json('attachments')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_helpdesk_tickets');
        Schema::dropIfExists('employee_loans');
    }
};
