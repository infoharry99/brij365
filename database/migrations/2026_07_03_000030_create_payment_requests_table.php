<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('collection_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('request_number', 40)->unique();
            $table->string('gateway_provider', 80)->default('prototype')->index();
            $table->string('gateway_reference', 120)->unique();
            $table->string('status')->default('requested');
            $table->decimal('amount', 16, 2);
            $table->string('currency', 3)->default('INR');
            $table->string('purpose', 160);
            $table->dateTime('expires_at')->nullable()->index();
            $table->dateTime('paid_at')->nullable()->index();
            $table->string('payment_mode', 40)->nullable();
            $table->string('instrument_number', 120)->nullable();
            $table->string('checksum', 128);
            $table->json('gateway_payload')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status', 'expires_at'], 'payment_requests_company_status_expires_index');
            $table->index(['customer_id', 'status'], 'payment_requests_customer_index');
            $table->index(['booking_payment_schedule_id', 'status'], 'payment_requests_schedule_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
