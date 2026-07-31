<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! (bool) config('builder360.single_company.enabled') || ! Schema::hasTable('companies')) {
            return;
        }

        $companyCode = (string) config('builder360.single_company.code');

        DB::table('companies')
            ->where('code', '<>', $companyCode)
            ->where('status', 'active')
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally no automatic reactivation. Restoring access to a
        // retained company is an explicit operator decision after rollback.
    }
};
