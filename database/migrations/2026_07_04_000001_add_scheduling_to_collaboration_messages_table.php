<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('collaboration_messages', 'scheduled_for')) {
                $table->dateTime('scheduled_for')->nullable()->after('recipient_archived_at');
                $table->index('scheduled_for', 'collaboration_messages_scheduled_for_index');
            }

            if (! Schema::hasColumn('collaboration_messages', 'sent_at')) {
                $table->dateTime('sent_at')->nullable()->after('scheduled_for');
                $table->index('sent_at', 'collaboration_messages_sent_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('collaboration_messages', 'sent_at')) {
                $table->dropIndex('collaboration_messages_sent_at_index');
                $table->dropColumn('sent_at');
            }

            if (Schema::hasColumn('collaboration_messages', 'scheduled_for')) {
                $table->dropIndex('collaboration_messages_scheduled_for_index');
                $table->dropColumn('scheduled_for');
            }
        });
    }
};
