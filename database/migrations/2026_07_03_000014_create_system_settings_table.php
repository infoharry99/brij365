<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_key', 64)->index();
            $table->string('setting_group', 80)->index();
            $table->string('setting_key', 160);
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('value_type', 32)->default('json')->index();
            $table->json('value');
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['scope_key', 'setting_key', 'version'], 'system_settings_scope_key_version_unique');
            $table->index(['scope_key', 'setting_key', 'status']);
            $table->index(['company_id', 'setting_group', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
