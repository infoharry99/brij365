<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_rosters', function (Blueprint $table): void {
            $table->json('rule_context')->nullable()->after('status_note');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_rosters', function (Blueprint $table): void {
            $table->dropColumn('rule_context');
        });
    }
};
