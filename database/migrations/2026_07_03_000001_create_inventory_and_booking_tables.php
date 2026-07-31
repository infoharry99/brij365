<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('unit_code', 40)->unique();
            $table->string('tower')->nullable()->index();
            $table->string('floor')->nullable()->index();
            $table->string('unit_number', 40)->index();
            $table->string('unit_type')->index();
            $table->decimal('carpet_area_sqft', 10, 2)->default(0);
            $table->decimal('saleable_area_sqft', 10, 2)->default(0);
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('base_price', 16, 2)->default(0);
            $table->decimal('floor_rise', 14, 2)->default(0);
            $table->decimal('parking_charges', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_price', 16, 2)->default(0);
            $table->string('status')->default('available')->index();
            $table->dateTime('reserved_until')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_code', 40)->unique();
            $table->string('status')->default('draft')->index();
            $table->date('booked_on')->index();
            $table->decimal('agreement_value', 16, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('net_receivable', 16, 2);
            $table->decimal('booking_amount', 14, 2)->default(0);
            $table->json('commercials')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('booking_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('milestone');
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('due_on')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->unique(['booking_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payment_schedules');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('project_units');
    }
};
