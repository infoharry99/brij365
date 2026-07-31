<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_calculation_snapshots', function (Blueprint $table): void {
            $table->json('input_snapshot')->nullable()->after('rule_context');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_calculation_snapshots', function (Blueprint $table): void {
            $table->dropColumn('input_snapshot');
        });
    }
};
