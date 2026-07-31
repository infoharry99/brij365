<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('portal_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        Schema::create('service_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ticket_number', 32)->unique();
            $table->string('category', 32)->index();
            $table->string('priority', 16)->index();
            $table->string('source', 32)->default('internal')->index();
            $table->string('subject');
            $table->text('description');
            $table->string('status', 32)->default('open')->index();
            $table->dateTime('first_response_due_at')->nullable()->index();
            $table->dateTime('first_responded_at')->nullable();
            $table->dateTime('sla_due_at')->nullable()->index();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->unsignedTinyInteger('customer_rating')->nullable();
            $table->json('attachments')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });

        Schema::create('maintenance_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_ticket_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('work_order_number', 32)->unique();
            $table->string('status', 32)->default('planned')->index();
            $table->date('scheduled_on')->nullable()->index();
            $table->text('scope_of_work');
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('actual_cost', 14, 2)->default(0);
            $table->json('materials_required')->nullable();
            $table->text('completion_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('workflow_history')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['service_ticket_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('service_tickets');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('portal_user_id');
        });
    }
};
