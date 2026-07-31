<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('disposition_outcome', 40)->nullable()->after('follow_up_at');
            $table->string('disposition_reason', 160)->nullable()->after('disposition_outcome');
            $table->string('competitor_name', 160)->nullable()->after('disposition_reason');
            $table->text('disposition_note')->nullable()->after('competitor_name');
            $table->foreignId('dispositioned_by_user_id')
                ->nullable()
                ->after('disposition_note')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('dispositioned_at')->nullable()->after('dispositioned_by_user_id');

            $table->index(['company_id', 'disposition_outcome']);
            $table->index(['dispositioned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'disposition_outcome']);
            $table->dropIndex(['dispositioned_at']);
            $table->dropConstrainedForeignId('dispositioned_by_user_id');
            $table->dropColumn([
                'disposition_outcome',
                'disposition_reason',
                'competitor_name',
                'disposition_note',
                'dispositioned_at',
            ]);
        });
    }
};
