<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('possession_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('handover_number', 40)->unique();
            $table->date('target_handover_on')->nullable()->index();
            $table->date('actual_handover_on')->nullable()->index();
            $table->string('status')->default('blocked')->index();
            $table->decimal('financial_outstanding', 16, 2)->default(0);
            $table->json('checklist');
            $table->json('blockers')->nullable();
            $table->string('possession_letter_reference')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('completed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['booking_id']);
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('handover_snags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('possession_handover_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('snag_number', 40)->unique();
            $table->string('area')->index();
            $table->string('category')->index();
            $table->string('severity')->default('medium')->index();
            $table->text('description');
            $table->string('status')->default('open')->index();
            $table->date('target_resolution_on')->nullable()->index();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->text('resolution_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->json('workflow_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'possession_handover_id', 'status'], 'handover_snag_scope_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handover_snags');
        Schema::dropIfExists('possession_handovers');
    }
};
