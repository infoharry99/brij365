<?php

namespace App\Services\Crm;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingCampaignService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function campaignRelations(): array
    {
        return ['company', 'project', 'createdBy', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function activityRelations(): array
    {
        return ['lead.customer', 'project', 'actor', 'marketingCampaign'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCampaign(array $data, User $actor, ?Request $request = null): MarketingCampaign
    {
        return DB::transaction(function () use ($data, $actor, $request): MarketingCampaign {
            $status = $data['status'] ?? 'draft';

            $campaign = MarketingCampaign::create([
                'company_id' => $data['company_id'],
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'approved_by_user_id' => $status === 'active' ? $actor->id : null,
                'campaign_code' => $this->nextCampaignCode(),
                'name' => $data['name'],
                'channel' => $data['channel'],
                'source' => $data['source'],
                'status' => $status,
                'start_on' => $data['start_on'],
                'end_on' => $data['end_on'] ?? null,
                'budget_amount' => $data['budget_amount'] ?? 0,
                'target_leads' => $data['target_leads'] ?? 0,
                'target_bookings' => $data['target_bookings'] ?? 0,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'audience_segment' => $data['audience_segment'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent($status, $actor, $status === 'active' ? 'Campaign created and activated' : 'Campaign created as draft'),
                ],
                'metadata' => $data['metadata'] ?? [],
                'approved_at' => $status === 'active' ? now() : null,
            ]);

            $this->auditLogger->record(
                $actor,
                'crm.campaign.created',
                'Created marketing campaign',
                $campaign,
                ['campaign_code' => $campaign->campaign_code, 'status' => $campaign->status],
                $request,
            );

            return $this->withMetrics($campaign->load($this->campaignRelations()));
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(MarketingCampaign $marketingCampaign, array $data, User $actor, ?Request $request = null): MarketingCampaign
    {
        return DB::transaction(function () use ($marketingCampaign, $data, $actor, $request): MarketingCampaign {
            $campaign = MarketingCampaign::query()->whereKey($marketingCampaign->id)->lockForUpdate()->firstOrFail();

            if ($campaign->status === 'archived') {
                throw ValidationException::withMessages(['status' => 'Archived campaigns cannot be reopened.']);
            }

            $history = $campaign->workflow_history ?? [];
            $history[] = $this->workflowEvent($data['status'], $actor, $data['note'] ?? 'Campaign status updated');

            $campaign->forceFill([
                'status' => $data['status'],
                'approved_by_user_id' => $data['status'] === 'active' ? $actor->id : $campaign->approved_by_user_id,
                'approved_at' => $data['status'] === 'active' ? now() : $campaign->approved_at,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.campaign.status_updated',
                'Updated marketing campaign status',
                $campaign,
                ['campaign_code' => $campaign->campaign_code, 'status' => $campaign->status],
                $request,
            );

            return $this->withMetrics($campaign->load($this->campaignRelations()));
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createLeadActivity(array $data, User $actor, ?Request $request = null): LeadActivity
    {
        return DB::transaction(function () use ($data, $actor, $request): LeadActivity {
            $lead = Lead::query()->whereKey($data['lead_id'])->lockForUpdate()->firstOrFail();
            $this->assertLeadCompany($lead, $actor);

            $campaignId = $data['marketing_campaign_id'] ?? $lead->marketing_campaign_id;
            $campaign = $campaignId ? MarketingCampaign::query()->whereKey($campaignId)->firstOrFail() : null;
            $this->assertCampaignMatchesLead($campaign, $lead);

            $activity = LeadActivity::create([
                'company_id' => $lead->company_id,
                'project_id' => $lead->project_id,
                'lead_id' => $lead->id,
                'actor_user_id' => $actor->id,
                'marketing_campaign_id' => $campaign?->id,
                'activity_number' => $this->nextActivityNumber(),
                'activity_type' => $data['activity_type'],
                'activity_at' => isset($data['activity_at']) ? Carbon::parse($data['activity_at']) : now(),
                'subject' => $data['subject'],
                'description' => $data['description'] ?? null,
                'outcome' => $data['outcome'] ?? null,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            if (isset($data['next_follow_up_at'])) {
                $lead->forceFill(['follow_up_at' => $data['next_follow_up_at']])->save();
            }

            $this->auditLogger->record(
                $actor,
                'crm.lead_activity.created',
                'Recorded lead activity',
                $activity,
                ['lead_code' => $lead->lead_code, 'activity_number' => $activity->activity_number, 'activity_type' => $activity->activity_type],
                $request,
            );

            return $activity->load($this->activityRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function recordSystemActivity(Lead $lead, User $actor, array $data): LeadActivity
    {
        $campaign = isset($data['marketing_campaign_id'])
            ? MarketingCampaign::query()->whereKey($data['marketing_campaign_id'])->first()
            : $lead->marketingCampaign;

        $this->assertCampaignMatchesLead($campaign, $lead);

        return LeadActivity::create([
            'company_id' => $lead->company_id,
            'project_id' => $lead->project_id,
            'lead_id' => $lead->id,
            'actor_user_id' => $actor->id,
            'marketing_campaign_id' => $campaign?->id,
            'activity_number' => $this->nextActivityNumber(),
            'activity_type' => $data['activity_type'],
            'activity_at' => $data['activity_at'] ?? now(),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'old_stage' => $data['old_stage'] ?? null,
            'new_stage' => $data['new_stage'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function withMetrics(MarketingCampaign $campaign): MarketingCampaign
    {
        $leadQuery = Lead::query()
            ->where('marketing_campaign_id', $campaign->id);

        $totalLeads = (clone $leadQuery)->count();
        $openLeads = (clone $leadQuery)->where('status', 'open')->count();
        $wonLeads = (clone $leadQuery)->where('status', 'won')->count();
        $lostLeads = (clone $leadQuery)->where('status', 'lost')->count();
        $expectedValue = (float) (clone $leadQuery)->sum('expected_value');
        $bookings = Booking::query()
            ->whereIn('lead_id', (clone $leadQuery)->select('id'))
            ->count();

        $campaign->setAttribute('metrics', [
            'total_leads' => $totalLeads,
            'open_leads' => $openLeads,
            'won_leads' => $wonLeads,
            'lost_leads' => $lostLeads,
            'bookings' => $bookings,
            'expected_value' => $expectedValue,
            'conversion_rate' => $totalLeads > 0 ? round(($wonLeads / $totalLeads) * 100, 2) : 0.0,
            'lead_target_attainment' => $campaign->target_leads > 0 ? round(($totalLeads / $campaign->target_leads) * 100, 2) : null,
            'booking_target_attainment' => $campaign->target_bookings > 0 ? round(($bookings / $campaign->target_bookings) * 100, 2) : null,
        ]);

        return $campaign;
    }

    public function assertLeadCompany(Lead $lead, User $actor): void
    {
        if (! $this->companyScope->allows($actor, $lead->company_id)) {
            throw ValidationException::withMessages(['lead_id' => 'The selected lead is not available for your company.']);
        }
    }

    private function assertCampaignMatchesLead(?MarketingCampaign $campaign, Lead $lead): void
    {
        if (! $campaign) {
            return;
        }

        if ((int) $campaign->company_id !== (int) $lead->company_id) {
            throw ValidationException::withMessages(['marketing_campaign_id' => 'The selected campaign must belong to the lead company.']);
        }

        if ($campaign->project_id !== null && $lead->project_id !== null && (int) $campaign->project_id !== (int) $lead->project_id) {
            throw ValidationException::withMessages(['marketing_campaign_id' => 'The selected campaign is not assigned to the lead project.']);
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextCampaignCode(): string
    {
        return sprintf('MC-%05d', MarketingCampaign::query()->withTrashed()->count() + 10001);
    }

    private function nextActivityNumber(): string
    {
        return sprintf('LA-%05d', LeadActivity::query()->withTrashed()->count() + 10001);
    }
}
