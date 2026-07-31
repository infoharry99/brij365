<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notification_number', 32)->unique();
            $table->string('channel', 32)->default('in_app')->index();
            $table->string('category', 64)->index();
            $table->string('severity', 16)->default('info')->index();
            $table->string('status', 24)->default('unread')->index();
            $table->string('title');
            $table->text('body');
            $table->string('action_url', 1024)->nullable();
            $table->string('notifiable_type')->nullable()->index();
            $table->unsignedBigInteger('notifiable_id')->nullable()->index();
            $table->json('payload')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['recipient_user_id', 'status']);
            $table->index(['company_id', 'category']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
