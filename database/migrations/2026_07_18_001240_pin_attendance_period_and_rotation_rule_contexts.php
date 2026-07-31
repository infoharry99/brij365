<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_period_locks', function (Blueprint $table): void {
            $table->json('rule_context')->nullable()->after('source_hash');
        });

        Schema::table('attendance_rotation_rules', function (Blueprint $table): void {
            $table->json('rule_context')->nullable()->after('generation_horizon_days');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_period_locks', function (Blueprint $table): void {
            $table->dropColumn('rule_context');
        });

        Schema::table('attendance_rotation_rules', function (Blueprint $table): void {
            $table->dropColumn('rule_context');
        });
    }
};
