<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('movement_number', 40)->unique();
            $table->string('movement_type', 40)->index();
            $table->date('effective_on')->index();
            $table->string('status', 40)->default('pending')->index();
            $table->longText('previous_values')->nullable();
            $table->longText('new_values');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id', 'movement_type']);
            $table->index(['employee_id', 'effective_on']);
            $table->index(['status', 'effective_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_movements');
    }
};
