<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('import_number', 32)->unique();
            $table->string('import_type', 80)->index();
            $table->string('source_filename')->nullable();
            $table->string('checksum', 64)->index();
            $table->string('status')->default('preview')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->json('source_rows')->nullable();
            $table->json('preview_rows')->nullable();
            $table->json('error_report')->nullable();
            $table->json('reconciliation_summary')->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('posted_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'import_type', 'status']);
            $table->index(['company_id', 'checksum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_batches');
    }
};
