<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->foreignId('score_snapshot_id')
                ->nullable()
                ->after('final_rating')
                ->constrained('score_snapshots')
                ->nullOnDelete();
        });

        Schema::create('performance_score_override_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_review_id');
            $table->foreign(
                'performance_review_id',
                'performance_override_review_fk',
            )->references('id')->on('performance_reviews')->cascadeOnDelete();
            $table->foreignId('score_snapshot_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('requested_score', 7, 4);
            $table->text('reason');
            $table->text('evidence')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('decision_reason')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at'], 'performance_override_company_status_index');
            $table->index(['performance_review_id', 'status'], 'performance_override_review_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_score_override_requests');

        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('score_snapshot_id');
        });
    }
};
