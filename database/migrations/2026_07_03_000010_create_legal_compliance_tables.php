<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rera_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registration_number', 80);
            $table->string('authority_name')->default('RERA')->index();
            $table->string('state_code', 10)->index();
            $table->date('registered_on')->index();
            $table->date('expires_on')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->string('document_reference')->nullable();
            $table->json('conditions')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('verified_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'registration_number']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('project_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_code', 80);
            $table->string('approval_type')->index();
            $table->string('authority_name')->index();
            $table->string('application_number')->nullable()->index();
            $table->date('applied_on')->nullable()->index();
            $table->date('approved_on')->nullable()->index();
            $table->date('expires_on')->nullable()->index();
            $table->string('status')->default('applied')->index();
            $table->string('required_for')->nullable()->index();
            $table->string('document_reference')->nullable();
            $table->json('conditions')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('verified_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'approval_code']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('compliance_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('obligation_number', 40)->unique();
            $table->string('title');
            $table->string('compliance_type')->index();
            $table->date('due_on')->index();
            $table->string('frequency')->default('one_time')->index();
            $table->string('priority')->default('normal')->index();
            $table->string('status')->default('open')->index();
            $table->string('evidence_document_reference')->nullable();
            $table->text('notes')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('completed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'status', 'due_on'], 'compliance_scope_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_obligations');
        Schema::dropIfExists('project_approvals');
        Schema::dropIfExists('rera_registrations');
    }
};
