<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->string('message_number')->unique();
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->default('sent');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
            $table->index(['company_id', 'project_id']);
            $table->index(['sender_user_id', 'status']);
        });

        Schema::create('chat_message_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('chat_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 32);
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id', 'emoji']);
        });

        Schema::create('chat_message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploader_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type')->default('file');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('scan_status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['chat_message_id', 'type']);
            $table->index(['company_id', 'scan_status']);
        });

        Schema::create('chat_polls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('question');
            $table->boolean('allows_multiple')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->timestamp('closes_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_poll_id')->constrained()->cascadeOnDelete();
            $table->string('option_text', 120);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('chat_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voter_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_poll_option_id', 'voter_user_id']);
            $table->index(['chat_poll_id', 'voter_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_poll_votes');
        Schema::dropIfExists('chat_poll_options');
        Schema::dropIfExists('chat_polls');
        Schema::dropIfExists('chat_message_attachments');
        Schema::dropIfExists('chat_message_reactions');
        Schema::dropIfExists('chat_message_reads');
        Schema::dropIfExists('chat_messages');
    }
};
