<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('calendar_events', 'lock_version')) {
            Schema::table('calendar_events', function (Blueprint $table): void {
                $table->unsignedInteger('lock_version')->default(1)->after('metadata');
                $table->uuid('client_token')->nullable()->after('lock_version');
                $table->foreignId('series_root_id')->nullable()->after('client_token')->constrained('calendar_events')->nullOnDelete();
                $table->string('occurrence_key', 160)->nullable()->after('series_root_id');
                $table->index(['company_id', 'ends_at'], 'cal_events_company_end_idx');
                $table->index(['company_id', 'organizer_user_id', 'starts_at'], 'cal_events_org_start_idx');
                $table->unique(['organizer_user_id', 'client_token'], 'cal_events_org_token_uq');
                $table->unique('occurrence_key', 'cal_events_occurrence_uq');
            });
        }

        if (! Schema::hasTable('calendar_event_attendees')) Schema::create('calendar_event_attendees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('attendee_type', 16)->default('internal');
            $table->string('response', 16)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['calendar_event_id', 'email']);
            $table->index(['user_id', 'response']);
            $table->index(['email', 'response']);
        });

        if (! Schema::hasTable('calendar_event_recurrence_rules')) Schema::create('calendar_event_recurrence_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('root_event_id')->unique()->constrained('calendar_events')->cascadeOnDelete();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->unsignedInteger('occurrence_limit')->nullable();
            $table->timestamp('until_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status', 'next_run_at'], 'cal_recur_company_status_run_idx');
        });

        if (! Schema::hasTable('calendar_event_reminder_deliveries')) Schema::create('calendar_event_reminder_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('calendar_event_id');
            $table->unsignedBigInteger('calendar_event_attendee_id')->nullable();
            $table->foreign('calendar_event_id', 'cal_reminder_event_fk')->references('id')->on('calendar_events')->cascadeOnDelete();
            $table->foreign('calendar_event_attendee_id', 'cal_reminder_attendee_fk')->references('id')->on('calendar_event_attendees')->cascadeOnDelete();
            $table->string('channel', 16)->default('in_app');
            $table->unsignedInteger('minutes_before');
            $table->timestamp('scheduled_for')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->string('idempotency_key', 160)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_for'], 'cal_reminder_status_schedule_idx');
        });

        if (! Schema::hasTable('calendar_event_attachments')) Schema::create('calendar_event_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('calendar_event_id');
            $table->unsignedBigInteger('uploaded_by_user_id');
            $table->foreign('calendar_event_id', 'cal_attach_event_fk')->references('id')->on('calendar_events')->cascadeOnDelete();
            $table->foreign('uploaded_by_user_id', 'cal_attach_user_fk')->references('id')->on('users')->restrictOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->string('scan_status', 16)->default('pending')->index();
            $table->timestamps();
            $table->index(['calendar_event_id', 'created_at'], 'cal_attach_event_created_idx');
        });

        DB::table('calendar_events')->orderBy('id')->chunkById(100, function ($events): void {
            foreach ($events as $event) {
                $attendees = json_decode($event->attendees ?: '[]', true) ?: [];
                foreach ($attendees as $attendee) {
                    $email = strtolower(trim((string) ($attendee['email'] ?? '')));
                    if ($email === '') {
                        continue;
                    }
                    DB::table('calendar_event_attendees')->updateOrInsert(
                        ['calendar_event_id' => $event->id, 'email' => $email],
                        [
                            'user_id' => $attendee['user_id'] ?? null,
                            'name' => $attendee['name'] ?? $email,
                            'attendee_type' => ! empty($attendee['user_id']) ? 'internal' : 'guest',
                            'response' => in_array(($attendee['response'] ?? 'pending'), ['pending', 'accepted', 'tentative', 'declined'], true) ? $attendee['response'] : 'pending',
                            'guest_token_hash' => empty($attendee['user_id']) ? hash('sha256', Str::uuid()->toString()) : null,
                            'invited_at' => $event->created_at,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_attachments');
        Schema::dropIfExists('calendar_event_reminder_deliveries');
        Schema::dropIfExists('calendar_event_recurrence_rules');
        Schema::dropIfExists('calendar_event_attendees');
        Schema::table('calendar_events', function (Blueprint $table): void {
            $table->dropForeign(['series_root_id']);
            $table->dropUnique(['organizer_user_id', 'client_token']);
            $table->dropUnique(['occurrence_key']);
            $table->dropIndex(['company_id', 'ends_at']);
            $table->dropIndex(['company_id', 'organizer_user_id', 'starts_at']);
            $table->dropColumn(['lock_version', 'client_token', 'series_root_id', 'occurrence_key']);
        });
    }
};
