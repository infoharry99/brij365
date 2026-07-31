<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_payment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number', 40)->unique();
            $table->string('status')->default('submitted')->index();
            $table->date('receipt_date')->index();
            $table->string('payment_mode', 40)->index();
            $table->string('instrument_number', 120)->nullable()->index();
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_deducted_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'status']);
            $table->index(['project_id', 'receipt_date']);
            $table->index(['customer_id', 'receipt_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_receipts');
    }
};
