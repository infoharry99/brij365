<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_pins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_key', 80);
            $table->string('label', 160);
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'report_key']);
            $table->index(['report_key', 'created_at']);
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_key', 80);
            $table->string('label', 160);
            $table->string('frequency', 32);
            $table->string('format', 20);
            $table->json('filters')->nullable();
            $table->json('recipients')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->dateTime('next_run_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['report_key', 'status']);
            $table->index(['next_run_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('report_pins');
    }
};
