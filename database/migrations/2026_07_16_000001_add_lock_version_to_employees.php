<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('employees', 'lock_version')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1)->after('sensitive_profile');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'lock_version')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->dropColumn('lock_version');
            });
        }
    }
};
