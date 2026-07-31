<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropForeign(['score_snapshot_id']);
            $table->foreign('score_snapshot_id')
                ->references('id')
                ->on('score_snapshots')
                ->restrictOnDelete();
        });

        Schema::table('score_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['overridden_from_snapshot_id']);
            $table->foreign('overridden_from_snapshot_id')
                ->references('id')
                ->on('score_snapshots')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropForeign(['score_snapshot_id']);
            $table->foreign('score_snapshot_id')
                ->references('id')
                ->on('score_snapshots')
                ->nullOnDelete();
        });

        Schema::table('score_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['overridden_from_snapshot_id']);
            $table->foreign('overridden_from_snapshot_id')
                ->references('id')
                ->on('score_snapshots')
                ->nullOnDelete();
        });
    }
};
