<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailbox_account_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mailbox_account_id')->constrained(indexName: 'mailbox_assignment_account_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(indexName: 'mailbox_assignment_user_fk')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users', indexName: 'mailbox_assignment_actor_fk')->nullOnDelete();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_send')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['mailbox_account_id', 'user_id'], 'mailbox_assignment_unique');
            $table->index(['user_id', 'can_view', 'is_default'], 'mailbox_assignment_user_lookup');
        });

        Schema::table('mailbox_outbox_attachments', function (Blueprint $table): void {
            $table->string('disposition', 16)->default('attachment')->after('checksum');
            $table->string('content_id')->nullable()->after('disposition');
            $table->string('scan_status', 16)->default('pending')->after('content_id');
        });

        $now = now();
        DB::table('mailbox_accounts')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->each(function (object $account) use ($now): void {
                DB::table('mailbox_account_user')->updateOrInsert(
                    ['mailbox_account_id' => $account->id, 'user_id' => $account->user_id],
                    [
                        'assigned_by_user_id' => $account->user_id,
                        'can_view' => true,
                        'can_send' => true,
                        'can_manage' => true,
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::table('mailbox_outbox_attachments', function (Blueprint $table): void {
            $table->dropColumn(['disposition', 'content_id', 'scan_status']);
        });
        Schema::dropIfExists('mailbox_account_user');
    }
};
