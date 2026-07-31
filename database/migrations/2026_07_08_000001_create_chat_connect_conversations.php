<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('conversation_key', 80)->unique();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('visibility', 40)->default('internal')->index();
            $table->string('department', 120)->nullable()->index();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('chat_conversation_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('member_role', 40)->default('member')->index();
            $table->boolean('can_post')->default(true);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_manage_members')->default(false);
            $table->boolean('muted')->default(false);
            $table->timestamp('last_read_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('removed_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['chat_conversation_id', 'user_id'], 'chat_members_conversation_user_unique');
            $table->index(['user_id', 'archived_at']);
        });

        if (Schema::hasTable('collaboration_messages') && ! Schema::hasColumn('collaboration_messages', 'chat_conversation_id')) {
            Schema::table('collaboration_messages', function (Blueprint $table): void {
                $table->foreignId('chat_conversation_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('chat_conversations')
                    ->nullOnDelete();
                $table->index(['chat_conversation_id', 'created_at'], 'collab_messages_chat_conversation_created_index');
            });

            $this->backfillExistingMessageThreads();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collaboration_messages') && Schema::hasColumn('collaboration_messages', 'chat_conversation_id')) {
            Schema::table('collaboration_messages', function (Blueprint $table): void {
                $table->dropIndex('collab_messages_chat_conversation_created_index');
                $table->dropConstrainedForeignId('chat_conversation_id');
            });
        }

        Schema::dropIfExists('chat_conversation_members');
        Schema::dropIfExists('chat_conversations');
    }

    private function backfillExistingMessageThreads(): void
    {
        $threadKeys = DB::table('collaboration_messages')
            ->select('thread_key')
            ->whereNull('chat_conversation_id')
            ->whereNotNull('thread_key')
            ->groupBy('thread_key')
            ->pluck('thread_key');

        foreach ($threadKeys as $threadKey) {
            $messages = DB::table('collaboration_messages')
                ->where('thread_key', $threadKey)
                ->orderBy('created_at')
                ->get();

            if ($messages->isEmpty()) {
                continue;
            }

            $first = $messages->first();
            $last = $messages->last();
            $memberIds = $messages
                ->flatMap(fn ($message) => [$message->sender_user_id, $message->recipient_user_id])
                ->filter()
                ->unique()
                ->values();

            $conversationId = DB::table('chat_conversations')->insertGetId([
                'company_id' => $first->company_id,
                'project_id' => $first->project_id,
                'owner_user_id' => $first->sender_user_id,
                'conversation_key' => 'legacy-'.$threadKey,
                'type' => $memberIds->count() <= 2 ? 'direct_message' : 'group_chat',
                'title' => preg_replace('/^Chat:\s*/i', '', (string) $first->subject) ?: 'Chat conversation',
                'description' => 'Imported from existing internal messages.',
                'visibility' => 'internal',
                'status' => 'active',
                'last_message_at' => $last->created_at,
                'metadata' => json_encode(['source' => 'legacy_collaboration_messages', 'thread_key' => $threadKey]),
                'created_at' => $first->created_at,
                'updated_at' => now(),
            ]);

            foreach ($memberIds as $index => $userId) {
                DB::table('chat_conversation_members')->insert([
                    'chat_conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'member_role' => (int) $userId === (int) $first->sender_user_id ? 'owner' : 'member',
                    'can_post' => true,
                    'can_upload' => false,
                    'can_manage_members' => $index === 0,
                    'created_at' => $first->created_at,
                    'updated_at' => now(),
                ]);
            }

            DB::table('collaboration_messages')
                ->where('thread_key', $threadKey)
                ->update(['chat_conversation_id' => $conversationId]);
        }
    }
};
