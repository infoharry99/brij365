<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_policy_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('acknowledged_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('policy_key', 120);
            $table->string('policy_title');
            $table->unsignedInteger('policy_version')->default(1);
            $table->string('status')->default('acknowledged')->index();
            $table->text('acknowledgement_note')->nullable();
            $table->json('policy_snapshot')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('acknowledged_at')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'policy_key', 'policy_version'], 'employee_policy_ack_unique');
            $table->index(['company_id', 'policy_key', 'policy_version', 'status'], 'employee_policy_ack_company_policy_index');
            $table->index(['employee_id', 'status', 'acknowledged_at'], 'employee_policy_ack_employee_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_policy_acknowledgements');
    }
};
