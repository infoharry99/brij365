<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['performance_reviews', 'interviews', 'vendors', 'projects', 'service_tickets', 'employee_exit_interviews'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->json('scoring_inputs')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->dropColumn('scoring_inputs');
            });
        }
    }
};
