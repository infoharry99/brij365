<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_message_id')->nullable()->constrained('collaboration_messages')->nullOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('message_number', 40)->unique();
            $table->string('thread_key', 40)->index();
            $table->string('subject');
            $table->text('body');
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 24)->default('unread')->index();
            $table->dateTime('read_at')->nullable()->index();
            $table->dateTime('recipient_archived_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'project_id']);
            $table->index(['recipient_user_id', 'status']);
            $table->index(['sender_user_id', 'created_at']);
            $table->index(['thread_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_messages');
    }
};
