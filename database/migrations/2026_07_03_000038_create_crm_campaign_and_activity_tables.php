<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campaign_code')->unique();
            $table->string('name');
            $table->string('channel', 40);
            $table->string('source', 80);
            $table->string('status', 24)->default('draft');
            $table->date('start_on');
            $table->date('end_on')->nullable();
            $table->decimal('budget_amount', 15, 2)->default(0);
            $table->unsignedInteger('target_leads')->default(0);
            $table->unsignedInteger('target_bookings')->default(0);
            $table->string('utm_source', 120)->nullable();
            $table->string('utm_medium', 120)->nullable();
            $table->string('utm_campaign', 120)->nullable();
            $table->string('audience_segment', 160)->nullable();
            $table->json('workflow_history')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'channel']);
            $table->index(['project_id', 'status']);
            $table->index(['start_on', 'end_on']);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('marketing_campaign_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('marketing_campaigns')
                ->nullOnDelete();

            $table->index(['company_id', 'marketing_campaign_id']);
            $table->index(['source', 'stage']);
        });

        Schema::create('lead_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('marketing_campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->string('activity_number')->unique();
            $table->string('activity_type', 40);
            $table->dateTime('activity_at');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('old_stage')->nullable();
            $table->string('new_stage')->nullable();
            $table->string('outcome', 80)->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lead_id', 'activity_at']);
            $table->index(['company_id', 'activity_type', 'activity_at']);
            $table->index(['project_id', 'activity_at']);
            $table->index(['marketing_campaign_id', 'activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activities');

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'marketing_campaign_id']);
            $table->dropIndex(['source', 'stage']);
            $table->dropConstrainedForeignId('marketing_campaign_id');
        });

        Schema::dropIfExists('marketing_campaigns');
    }
};
