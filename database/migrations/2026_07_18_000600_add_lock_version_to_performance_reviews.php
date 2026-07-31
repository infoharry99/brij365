<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('performance_reviews', 'lock_version')) {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1)->after('legacy_manual_scoring');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('performance_reviews', 'lock_version')) {
            Schema::table('performance_reviews', function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
