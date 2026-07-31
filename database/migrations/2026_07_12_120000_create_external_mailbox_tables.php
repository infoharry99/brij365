<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('status', 24)->default('pending');
            $table->string('imap_host');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 12)->nullable();
            $table->boolean('imap_validate_cert')->default(true);
            $table->string('smtp_host');
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 12)->nullable();
            $table->string('username');
            $table->text('secret');
            $table->boolean('sync_enabled')->default(true);
            $table->unsignedSmallInteger('sync_interval_minutes')->default(5);
            $table->timestamp('last_connection_tested_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'email']);
            $table->index(['status', 'sync_enabled', 'last_synced_at']);
        });

        Schema::create('mailbox_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('remote_path');
            $table->string('delimiter', 8)->nullable();
            $table->string('special_use', 24)->nullable();
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->unsignedBigInteger('uid_next')->nullable();
            $table->unsignedBigInteger('last_synced_uid')->nullable();
            $table->boolean('is_selectable')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['mailbox_account_id', 'remote_path']);
            $table->index(['mailbox_account_id', 'special_use']);
        });

        Schema::create('mailbox_emails', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mailbox_folder_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('remote_uid');
            $table->string('internet_message_id')->nullable()->index();
            $table->string('thread_key')->nullable()->index();
            $table->string('in_reply_to')->nullable();
            $table->json('references')->nullable();
            $table->string('subject')->nullable();
            $table->json('from_addresses')->nullable();
            $table->json('to_addresses')->nullable();
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();
            $table->json('reply_to_addresses')->nullable();
            $table->mediumText('text_body')->nullable();
            $table->mediumText('html_body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('flags')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->string('sync_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['mailbox_folder_id', 'remote_uid']);
            $table->index(['mailbox_account_id', 'received_at']);
            $table->index(['mailbox_folder_id', 'is_read', 'is_flagged']);
        });

        Schema::create('mailbox_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_email_id')->constrained()->cascadeOnDelete();
            $table->string('content_id')->nullable();
            $table->string('filename');
            $table->string('mime_type', 180)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('checksum', 64);
            $table->boolean('is_inline')->default(false);
            $table->timestamps();
            $table->unique(['mailbox_email_id', 'checksum']);
        });

        Schema::create('mailbox_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('folders_processed')->default(0);
            $table->unsignedInteger('messages_created')->default(0);
            $table->unsignedInteger('messages_updated')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
            $table->index(['mailbox_account_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_sync_runs');
        Schema::dropIfExists('mailbox_attachments');
        Schema::dropIfExists('mailbox_emails');
        Schema::dropIfExists('mailbox_folders');
        Schema::dropIfExists('mailbox_accounts');
    }
};
