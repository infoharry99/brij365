<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'audit_events_user_created_index');
            $table->index(['event_type', 'created_at'], 'audit_events_type_created_index');
            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_events_auditable_created_index');
            $table->index(['request_id', 'request_method', 'created_at'], 'audit_events_request_context_index');
        });

        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->index(['recipient_user_id', 'created_at'], 'notifications_recipient_created_index');
            $table->index(['recipient_user_id', 'status', 'created_at'], 'notifications_recipient_status_created_index');
            $table->index(['recipient_user_id', 'category', 'status', 'created_at'], 'notifications_recipient_category_status_index');
            $table->index(['recipient_user_id', 'severity', 'status', 'created_at'], 'notifications_recipient_severity_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_recipient_severity_status_index');
            $table->dropIndex('notifications_recipient_category_status_index');
            $table->dropIndex('notifications_recipient_status_created_index');
            $table->dropIndex('notifications_recipient_created_index');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex('audit_events_request_context_index');
            $table->dropIndex('audit_events_auditable_created_index');
            $table->dropIndex('audit_events_type_created_index');
            $table->dropIndex('audit_events_user_created_index');
        });
    }
};
