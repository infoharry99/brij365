<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shift_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_shift_id')->constrained('attendance_shifts')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('label', 80)->nullable();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['attendance_shift_id', 'sequence'], 'attendance_shift_segment_sequence_unique');
        });

        Schema::create('attendance_source_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('work_date')->index();
            $table->dateTime('occurred_at')->index();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('event_type', 32)->index();
            $table->string('source', 48)->index();
            $table->string('source_reference', 191)->nullable();
            $table->string('event_key', 64)->unique();
            $table->string('payload_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(
                ['company_id', 'employee_id', 'work_date', 'occurred_at'],
                'attendance_source_employee_date_index',
            );
        });

        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->string('source_hash', 64)->nullable()->after('metadata');
            $table->json('calculation_trace')->nullable()->after('source_hash');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropColumn(['source_hash', 'calculation_trace']);
        });

        Schema::dropIfExists('attendance_source_events');
        Schema::dropIfExists('attendance_shift_segments');
    }
};
