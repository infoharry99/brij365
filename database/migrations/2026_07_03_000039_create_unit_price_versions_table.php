<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_price_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('price_code')->unique();
            $table->unsignedInteger('version_number');
            $table->string('status', 24)->default('draft');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('base_rate', 12, 2);
            $table->decimal('base_price', 16, 2);
            $table->decimal('floor_premium', 14, 2)->default(0);
            $table->decimal('location_premium', 14, 2)->default(0);
            $table->decimal('parking_charges', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('tax_rate_percent', 7, 4)->default(0);
            $table->decimal('gross_price_before_tax', 16, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_price', 16, 2)->default(0);
            $table->json('charge_breakup')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_unit_id', 'version_number']);
            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['project_unit_id', 'status', 'effective_from']);
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('unit_price_version_id')
                ->nullable()
                ->after('project_unit_id')
                ->constrained('unit_price_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_price_version_id');
        });

        Schema::dropIfExists('unit_price_versions');
    }
};
