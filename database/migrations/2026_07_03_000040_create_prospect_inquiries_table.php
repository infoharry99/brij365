<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('converted_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('duplicate_of_id')->nullable()->constrained('prospect_inquiries')->nullOnDelete();
            $table->string('inquiry_number', 32)->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 32)->nullable()->index();
            $table->string('source', 80)->default('Website')->index();
            $table->string('channel', 40)->default('website')->index();
            $table->string('preferred_contact_method', 32)->nullable();
            $table->string('status')->default('new')->index();
            $table->decimal('budget_min', 14, 2)->nullable();
            $table->decimal('budget_max', 14, 2)->nullable();
            $table->text('message')->nullable();
            $table->boolean('consent_to_contact')->default(false);
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('assigned_at')->nullable()->index();
            $table->dateTime('converted_at')->nullable()->index();
            $table->dateTime('closed_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['source', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_inquiries');
    }
};
