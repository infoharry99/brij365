<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->string('direct_pair_key', 120)->nullable()->after('conversation_key');
        });

        $claimedPairs = [];

        DB::table('chat_conversations')
            ->where('type', 'direct_message')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'company_id'])
            ->each(function (object $conversation) use (&$claimedPairs): void {
                $memberIds = DB::table('chat_conversation_members')
                    ->where('chat_conversation_id', $conversation->id)
                    ->whereNull('removed_at')
                    ->orderBy('user_id')
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                if ($memberIds->count() !== 2) {
                    return;
                }

                $pairKey = sprintf('%d:%d:%d', $conversation->company_id, $memberIds[0], $memberIds[1]);
                if (isset($claimedPairs[$pairKey])) {
                    return;
                }

                DB::table('chat_conversations')
                    ->where('id', $conversation->id)
                    ->update(['direct_pair_key' => $pairKey]);

                $claimedPairs[$pairKey] = true;
            });

        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->unique('direct_pair_key', 'chat_conversations_direct_pair_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropUnique('chat_conversations_direct_pair_key_unique');
            $table->dropColumn('direct_pair_key');
        });
    }
};
