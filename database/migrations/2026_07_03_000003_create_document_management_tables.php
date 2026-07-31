<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('owner_type')->default('global')->index();
            $table->boolean('expiry_required')->default(false)->index();
            $table->unsignedSmallInteger('reminder_days_before_expiry')->default(30);
            $table->unsignedSmallInteger('retention_years')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('managed_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_number', 60)->unique();
            $table->string('title');
            $table->string('owner_type')->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->string('storage_disk')->default('local');
            $table->string('storage_path', 1024);
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('checksum_sha256', 64);
            $table->date('issue_date')->nullable()->index();
            $table->date('expires_on')->nullable()->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'owner_type', 'owner_id']);
            $table->index(['document_category_id', 'status']);
            $table->index(['company_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_documents');
        Schema::dropIfExists('document_categories');
    }
};
