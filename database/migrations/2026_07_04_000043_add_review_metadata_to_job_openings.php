<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('reviewed_at')
                ->nullable()
                ->after('target_hiring_date')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->dropColumn('reviewed_at');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
        });
    }
};
