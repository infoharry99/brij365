<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->string('request_method', 12)->nullable()->index();
            $table->string('request_path', 255)->nullable()->index();
            $table->string('request_id', 120)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropColumn([
                'request_method',
                'request_path',
                'request_id',
                'user_agent',
            ]);
        });
    }
};
