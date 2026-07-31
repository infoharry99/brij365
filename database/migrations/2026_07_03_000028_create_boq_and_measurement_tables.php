<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boq_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('construction_milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('boq_code', 60);
            $table->string('trade')->index();
            $table->string('description');
            $table->string('unit', 40);
            $table->decimal('planned_quantity', 14, 3);
            $table->decimal('rate', 14, 2);
            $table->decimal('budget_amount', 16, 2)->default(0);
            $table->decimal('measured_quantity', 14, 3)->default(0);
            $table->decimal('certified_quantity', 14, 3)->default(0);
            $table->decimal('certified_amount', 16, 2)->default(0);
            $table->string('status')->default('active')->index();
            $table->json('specifications')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'boq_code']);
            $table->index(['company_id', 'project_id', 'trade', 'status'], 'boq_items_scope_index');
        });

        Schema::create('contractor_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('measurement_number', 40)->unique();
            $table->date('measurement_date')->index();
            $table->string('bill_reference', 80)->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->decimal('measured_total', 16, 2)->default(0);
            $table->decimal('certified_total', 16, 2)->default(0);
            $table->json('lines');
            $table->text('remarks')->nullable();
            $table->json('workflow_history')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id', 'vendor_id', 'status'], 'contractor_measurements_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_measurements');
        Schema::dropIfExists('boq_items');
    }
};
