<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->decimal('annual_entitlement_days', 6, 2)->default(0);
            $table->boolean('is_paid')->default(true)->index();
            $table->boolean('requires_document')->default(false)->index();
            $table->boolean('allows_half_day')->default(true);
            $table->boolean('allow_negative_balance')->default(false);
            $table->boolean('carry_forward_enabled')->default(false);
            $table->decimal('max_carry_forward_days', 6, 2)->default(0);
            $table->boolean('encashment_enabled')->default(false);
            $table->json('approval_chain')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('employee_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_year')->index();
            $table->decimal('opening_balance_days', 6, 2)->default(0);
            $table->decimal('accrued_days', 6, 2)->default(0);
            $table->decimal('used_days', 6, 2)->default(0);
            $table->decimal('pending_days', 6, 2)->default(0);
            $table->decimal('adjusted_days', 6, 2)->default(0);
            $table->decimal('available_days', 6, 2)->default(0);
            $table->json('ledger')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_year'], 'employee_leave_balance_unique');
            $table->index(['company_id', 'period_year']);
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('supporting_document_id')->nullable()->constrained('managed_documents')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_number', 40)->unique();
            $table->string('status')->default('submitted')->index();
            $table->date('starts_on')->index();
            $table->date('ends_on')->index();
            $table->string('duration_unit')->default('full_day');
            $table->decimal('requested_days', 6, 2);
            $table->text('reason')->nullable();
            $table->text('decision_note')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('decided_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['company_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
