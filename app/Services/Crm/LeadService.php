<?php

namespace App\Services\Crm;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly MarketingCampaignService $campaignService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?Request $request = null): Lead
    {
        return DB::transaction(function () use ($data, $actor, $request): Lead {
            $customer = Customer::query()
                ->when($data['customer_email'] ?? null, fn ($query, string $email) => $query->orWhere('email', $email))
                ->when($data['customer_phone'] ?? null, fn ($query, string $phone) => $query->orWhere('phone', $phone))
                ->first();

            if ($customer) {
                $customer->fill([
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'] ?? $customer->email,
                    'phone' => $data['customer_phone'] ?? $customer->phone,
                    'source' => $data['source'],
                    'status' => 'active',
                ])->save();
            } else {
                $customer = Customer::create([
                    'code' => $this->nextCode('CUS', Customer::query()->withTrashed()->count() + 1),
                    'name' => $data['customer_name'],
                    'email' => $data['customer_email'] ?? null,
                    'phone' => $data['customer_phone'] ?? null,
                    'source' => $data['source'],
                    'status' => 'active',
                ]);
            }

            $lead = Lead::create([
                'company_id' => $data['company_id'],
                'project_id' => $data['project_id'] ?? null,
                'customer_id' => $customer->id,
                'partner_id' => $data['partner_id'] ?? null,
                'marketing_campaign_id' => $data['marketing_campaign_id'] ?? null,
                'owner_user_id' => $actor->id,
                'lead_code' => $this->nextCode('LD', Lead::query()->withTrashed()->count() + 1),
                'source' => $data['source'],
                'stage' => $data['stage'],
                'status' => $data['status'] ?? 'open',
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'expected_value' => $data['expected_value'],
                'follow_up_at' => $data['follow_up_at'] ?? null,
            ]);

            $this->auditLogger->record(
                $actor,
                'crm.lead.created',
                'Created CRM lead',
                $lead,
                ['lead_code' => $lead->lead_code, 'stage' => $lead->stage],
                $request,
            );

            $this->campaignService->recordSystemActivity($lead, $actor, [
                'marketing_campaign_id' => $lead->marketing_campaign_id,
                'activity_type' => 'created',
                'subject' => 'Lead created',
                'description' => 'Lead captured from '.$lead->source.'.',
                'new_stage' => $lead->stage,
                'outcome' => $lead->status,
                'next_follow_up_at' => $lead->follow_up_at,
                'metadata' => ['lead_code' => $lead->lead_code],
            ]);

            if ($lead->marketing_campaign_id) {
                $this->campaignService->recordSystemActivity($lead, $actor, [
                    'marketing_campaign_id' => $lead->marketing_campaign_id,
                    'activity_type' => 'campaign_response',
                    'subject' => 'Campaign response captured',
                    'description' => 'Lead attributed to campaign from source '.$lead->source.'.',
                    'new_stage' => $lead->stage,
                    'outcome' => 'lead_created',
                    'metadata' => ['lead_code' => $lead->lead_code],
                ]);
            }

            return $lead->load(['company', 'project', 'customer', 'partner', 'marketingCampaign', 'owner']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStage(Lead $lead, array $data, User $actor, ?Request $request = null): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor, $request): Lead {
            $before = [
                'stage' => $lead->stage,
                'status' => $lead->status,
                'follow_up_at' => $lead->follow_up_at?->toISOString(),
            ];

            $lead->fill([
                'stage' => $data['stage'],
                'status' => $data['status'] ?? $lead->status,
                'follow_up_at' => $data['follow_up_at'] ?? $lead->follow_up_at,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.lead.stage_updated',
                'Updated CRM lead stage',
                $lead,
                [
                    'before' => $before,
                    'after' => [
                        'stage' => $lead->stage,
                        'status' => $lead->status,
                        'follow_up_at' => $lead->follow_up_at?->toISOString(),
                    ],
                ],
                $request,
            );

            $this->campaignService->recordSystemActivity($lead, $actor, [
                'activity_type' => 'stage_change',
                'subject' => 'Lead stage changed',
                'description' => 'Lead stage changed from '.$before['stage'].' to '.$lead->stage.'.',
                'old_stage' => $before['stage'],
                'new_stage' => $lead->stage,
                'outcome' => $lead->status,
                'next_follow_up_at' => $lead->follow_up_at,
                'metadata' => ['lead_code' => $lead->lead_code],
            ]);

            return $lead->load(['company', 'project', 'customer', 'partner', 'marketingCampaign', 'owner']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dispose(Lead $lead, array $data, User $actor, ?Request $request = null): Lead
    {
        return DB::transaction(function () use ($lead, $data, $actor, $request): Lead {
            $before = [
                'stage' => $lead->stage,
                'status' => $lead->status,
                'follow_up_at' => $lead->follow_up_at?->toISOString(),
                'disposition_outcome' => $lead->disposition_outcome,
            ];

            $outcome = $data['outcome'];
            $stage = $outcome === 'deferred' ? 'Follow-up' : 'Lost';
            $status = $outcome === 'deferred' ? 'on_hold' : 'lost';
            $followUpAt = $outcome === 'deferred' ? $data['follow_up_at'] : null;

            $lead->fill([
                'stage' => $stage,
                'status' => $status,
                'follow_up_at' => $followUpAt,
                'disposition_outcome' => $outcome,
                'disposition_reason' => $data['reason'],
                'competitor_name' => $data['competitor_name'] ?? null,
                'disposition_note' => $data['note'] ?? null,
                'dispositioned_by_user_id' => $actor->id,
                'dispositioned_at' => now(),
            ])->save();

            $after = [
                'stage' => $lead->stage,
                'status' => $lead->status,
                'follow_up_at' => $lead->follow_up_at?->toISOString(),
                'disposition_outcome' => $lead->disposition_outcome,
                'disposition_reason' => $lead->disposition_reason,
            ];

            $this->auditLogger->record(
                $actor,
                'crm.lead.dispositioned',
                'Dispositioned CRM lead',
                $lead,
                [
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            $this->campaignService->recordSystemActivity($lead, $actor, [
                'activity_type' => 'disposition',
                'subject' => 'Lead disposition recorded',
                'description' => 'Lead disposition recorded as '.$outcome.' because '.$data['reason'].'.',
                'old_stage' => $before['stage'],
                'new_stage' => $lead->stage,
                'outcome' => $outcome,
                'next_follow_up_at' => $lead->follow_up_at,
                'metadata' => [
                    'lead_code' => $lead->lead_code,
                    'reason' => $data['reason'],
                    'competitor_name' => $data['competitor_name'] ?? null,
                ],
            ]);

            return $lead->load(['company', 'project', 'customer', 'partner', 'marketingCampaign', 'owner', 'dispositionedBy']);
        });
    }

    private function nextCode(string $prefix, int $number): string
    {
        return sprintf('%s-%04d', $prefix, $number);
    }
}
