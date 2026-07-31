<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('internal_mailbox_dispatches',function(Blueprint $table):void{
            $table->id();$table->foreignId('company_id')->constrained()->cascadeOnDelete();$table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();$table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('client_token');$table->string('thread_key',40)->index();$table->foreignId('parent_dispatch_id')->nullable()->constrained('internal_mailbox_dispatches')->nullOnDelete();$table->foreignId('parent_message_id')->nullable()->constrained('collaboration_messages')->nullOnDelete();
            $table->string('subject')->nullable();$table->mediumText('body')->nullable();$table->string('priority',16)->default('normal');$table->string('state',24)->default('draft');
            $table->timestamp('scheduled_for')->nullable();$table->timestamp('sent_at')->nullable();$table->timestamp('failed_at')->nullable();$table->text('last_error')->nullable();$table->unsignedSmallInteger('attempt_count')->default(0);$table->unsignedInteger('lock_version')->default(1);$table->timestamps();
            $table->unique(['sender_user_id','client_token'],'internal_dispatch_sender_token_unique');$table->index(['company_id','state']);$table->index(['sender_user_id','state']);
        });
        Schema::create('internal_mailbox_dispatch_recipients',function(Blueprint $table):void{
            $table->id();$table->foreignId('internal_mailbox_dispatch_id')->constrained(indexName:'internal_dispatch_recipient_dispatch_fk')->cascadeOnDelete();$table->foreignId('user_id')->constrained(indexName:'internal_dispatch_recipient_user_fk')->restrictOnDelete();$table->string('recipient_type',8)->default('to');$table->timestamps();
            $table->unique(['internal_mailbox_dispatch_id','user_id'],'internal_dispatch_recipient_unique');$table->index(['user_id','recipient_type'],'internal_dispatch_recipient_lookup');
        });
        Schema::create('internal_mailbox_attachments',function(Blueprint $table):void{
            $table->id();$table->foreignId('internal_mailbox_dispatch_id')->constrained(indexName:'internal_attachment_dispatch_fk')->cascadeOnDelete();$table->foreignId('uploaded_by_user_id')->constrained('users', indexName:'internal_attachment_uploader_fk')->restrictOnDelete();$table->string('original_filename');$table->string('mime_type',180);$table->unsignedBigInteger('size_bytes');$table->string('disk',32)->default('local');$table->string('path');$table->string('checksum_sha256',64);$table->string('scan_status',16)->default('pending');$table->timestamps();
            $table->unique(['internal_mailbox_dispatch_id','checksum_sha256'],'internal_dispatch_attachment_checksum_unique');
        });
        Schema::table('collaboration_messages',function(Blueprint $table):void{$table->foreignId('internal_mailbox_dispatch_id')->nullable()->after('chat_conversation_id')->constrained()->nullOnDelete();$table->index(['internal_mailbox_dispatch_id','recipient_user_id'],'collab_message_dispatch_recipient_index');});
    }
    public function down():void{Schema::table('collaboration_messages',function(Blueprint $table):void{$table->dropForeign(['internal_mailbox_dispatch_id']);$table->dropIndex('collab_message_dispatch_recipient_index');$table->dropColumn('internal_mailbox_dispatch_id');});Schema::dropIfExists('internal_mailbox_attachments');Schema::dropIfExists('internal_mailbox_dispatch_recipients');Schema::dropIfExists('internal_mailbox_dispatches');}
};
