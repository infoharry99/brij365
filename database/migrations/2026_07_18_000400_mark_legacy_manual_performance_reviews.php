<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->boolean('legacy_manual_scoring')->default(false)->after('score_snapshot_id');
        });

        // Rows created before governed scoring existed retain an explicit, auditable
        // compatibility path. New reviews always require a pinned formula snapshot.
        DB::table('performance_reviews')->update(['legacy_manual_scoring' => true]);
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropColumn('legacy_manual_scoring');
        });
    }
};
