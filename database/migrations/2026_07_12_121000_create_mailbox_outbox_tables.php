<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mailbox_outbox_messages', function(Blueprint $table): void {
            $table->id(); $table->foreignId('mailbox_account_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_token'); $table->string('state',24)->default('draft');
            $table->json('to_addresses')->nullable(); $table->json('cc_addresses')->nullable(); $table->json('bcc_addresses')->nullable();
            $table->string('subject')->nullable(); $table->mediumText('text_body')->nullable(); $table->mediumText('html_body')->nullable();
            $table->string('in_reply_to')->nullable(); $table->text('references_header')->nullable();
            $table->timestamp('scheduled_for')->nullable(); $table->timestamp('sent_at')->nullable(); $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0); $table->string('provider_message_id')->nullable(); $table->text('last_error')->nullable();
            $table->unsignedInteger('lock_version')->default(1); $table->timestamps();
            $table->unique(['mailbox_account_id','client_token']); $table->index(['state','scheduled_for']);
        });
        Schema::create('mailbox_outbox_attachments', function(Blueprint $table): void {
            $table->id(); $table->foreignId('mailbox_outbox_message_id')->constrained()->cascadeOnDelete(); $table->string('filename'); $table->string('mime_type',180)->nullable();
            $table->unsignedBigInteger('size'); $table->string('disk',32)->default('local'); $table->string('path'); $table->string('checksum',64); $table->timestamps();
            $table->unique(['mailbox_outbox_message_id','checksum'],'mbx_outbox_attachment_checksum_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('mailbox_outbox_attachments'); Schema::dropIfExists('mailbox_outbox_messages'); }
};
