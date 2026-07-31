<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreignId('employee_id')
                ->nullable()
                ->after('owner_user_id')
                ->constrained('employees')
                ->nullOnDelete();
            $table->unique('employee_id', 'candidates_employee_id_unique');
        });

        Schema::table('job_offers', function (Blueprint $table): void {
            $table->foreignId('accepted_by_user_id')
                ->nullable()
                ->after('released_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('accepted_at')
                ->nullable()
                ->after('released_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table): void {
            $table->dropColumn('accepted_at');
            $table->dropConstrainedForeignId('accepted_by_user_id');
        });

        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropUnique('candidates_employee_id_unique');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
