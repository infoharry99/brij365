<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_tasks', function (Blueprint $table): void {
            $table->uuid('client_token')->nullable()->unique()->after('task_number');
        });

        Schema::create('work_task_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_task_id')->constrained('work_tasks')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024);
            $table->string('original_filename');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('scan_status', 24)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'work_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_task_attachments');
        Schema::table('work_tasks', fn (Blueprint $table) => $table->dropColumn('client_token'));
    }
};
