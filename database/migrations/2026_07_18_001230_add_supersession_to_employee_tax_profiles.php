<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_tax_profiles', function (Blueprint $table): void {
            $table->dropUnique('employee_tax_profile_year_unique');
            $table->foreignId('supersedes_employee_tax_profile_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('employee_tax_profiles')
                ->restrictOnDelete();
            $table->unique(
                ['company_id', 'employee_id', 'financial_year', 'version'],
                'employee_tax_profile_year_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_tax_profiles', function (Blueprint $table): void {
            $table->dropUnique('employee_tax_profile_year_version_unique');
            $table->dropConstrainedForeignId('supersedes_employee_tax_profile_id');
            $table->unique(
                ['company_id', 'employee_id', 'financial_year'],
                'employee_tax_profile_year_unique',
            );
        });
    }
};
