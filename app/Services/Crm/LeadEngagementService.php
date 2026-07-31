<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadEngagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
        private readonly MarketingCampaignService $campaignService,
        private readonly LeadQualityScoreService $qualityScoreService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function qualify(array $data, User $actor, ?Request $request = null): LeadQualification
    {
        return DB::transaction(function () use ($data, $actor, $request): LeadQualification {
            $lead = Lead::query()->whereKey($data['lead_id'])->lockForUpdate()->firstOrFail();
            $this->assertLeadCompany($lead, $actor);

            $qualityScore = $this->qualityScoreService->calculate($lead, $data);
            $score = $qualityScore['score'];
            $components = $qualityScore['components'];
            $this->assertConfiguredScoreRouting($qualityScore, $data['status']);
            $metadata = $data['metadata'] ?? [];
            $metadata['quality_score'] = [
                'score' => $score,
                'raw_score' => $qualityScore['raw_score'],
                'max_score' => $qualityScore['max_score'],
                'band' => $qualityScore['band'],
                'components' => $components,
                'labels' => $qualityScore['labels'],
                'selected_conditions' => $qualityScore['selected_conditions'],
                'rules' => $qualityScore['rules'],
                'calculated_at' => now()->toISOString(),
            ];

            $qualification = LeadQualification::create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'qualified_by_user_id' => $actor->id,
                'qualification_number' => $this->nextQualificationNumber(),
                'status' => $data['status'],
                'score' => $score,
                'budget_score' => $components['budget'] ?? 0,
                'authority_score' => $components['authority'] ?? 0,
                'need_score' => $components['need'] ?? 0,
                'timeline_score' => $components['timeline'] ?? 0,
                'preferred_configuration' => $data['preferred_configuration'] ?? null,
                'verified_budget_min' => $data['verified_budget_min'] ?? null,
                'verified_budget_max' => $data['verified_budget_max'] ?? null,
                'expected_booking_date' => $data['expected_booking_date'] ?? null,
                'decision_notes' => $data['decision_notes'],
                'requirements' => $data['requirements'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent($data['status'], $actor, 'Lead qualification recorded'),
                ],
                'metadata' => $metadata,
                'qualified_at' => now(),
            ]);

            $lead->forceFill([
                'stage' => match ($data['status']) {
                    'qualified' => 'Qualified',
                    'nurture' => 'Nurture',
                    default => 'Disqualified',
                },
                'status' => $data['status'] === 'disqualified' ? 'lost' : 'open',
                'budget_min' => $data['verified_budget_min'] ?? $lead->budget_min,
                'budget_max' => $data['verified_budget_max'] ?? $lead->budget_max,
                'follow_up_at' => $data['expected_booking_date'] ?? $lead->follow_up_at,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.lead.qualified',
                'Recorded lead qualification',
                $qualification,
                [
                    'lead_code' => $lead->lead_code,
                    'qualification_number' => $qualification->qualification_number,
                    'score' => $score,
                    'score_band' => $qualityScore['band']['label'] ?? null,
                    'score_rule_source' => $qualityScore['rules']['source'] ?? null,
                    'score_rule_version' => $qualityScore['rules']['version'] ?? null,
                    'status' => $data['status'],
                ],
                $request,
            );

            $this->campaignService->recordSystemActivity($lead, $actor, [
                'activity_type' => 'qualification',
                'subject' => 'Lead qualification recorded',
                'description' => $data['decision_notes'],
                'old_stage' => null,
                'new_stage' => $lead->stage,
                'outcome' => $data['status'],
                'next_follow_up_at' => $lead->follow_up_at,
                'metadata' => [
                    'qualification_number' => $qualification->qualification_number,
                    'score' => $score,
                    'score_band' => $qualityScore['band']['label'] ?? null,
                    'score_rule_source' => $qualityScore['rules']['source'] ?? null,
                    'score_rule_version' => $qualityScore['rules']['version'] ?? null,
                ],
            ]);

            return $qualification->load($this->qualificationRelations());
        });
    }

    /**
     * @param array<string, mixed> $qualityScore
     */
    private function assertConfiguredScoreRouting(array $qualityScore, string $submittedStatus): void
    {
        $band = is_array($qualityScore['band'] ?? null) ? $qualityScore['band'] : [];
        $configuredStatus = (string) ($band['status_hint'] ?? '');
        $allowedStatuses = ['qualified', 'nurture', 'disqualified'];

        if (! in_array($configuredStatus, $allowedStatuses, true)) {
            return;
        }

        if ($submittedStatus !== $configuredStatus) {
            $label = (string) ($band['label'] ?? 'selected score band');

            throw ValidationException::withMessages([
                'status' => "The submitted qualification status must be {$configuredStatus} for the {$label} score band.",
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function scheduleSiteVisit(array $data, User $actor, ?Request $request = null): SiteVisit
    {
        return DB::transaction(function () use ($data, $actor, $request): SiteVisit {
            $lead = Lead::query()
                ->with(['customer', 'project'])
                ->whereKey($data['lead_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertLeadCompany($lead, $actor);

            if ($lead->status === 'lost') {
                throw ValidationException::withMessages(['lead_id' => 'Site visits cannot be scheduled for lost leads.']);
            }

            $assignee = isset($data['assigned_to_user_id'])
                ? User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail()
                : $actor;

            if ($assignee->company_id !== $lead->company_id) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The assigned user must belong to the lead company.']);
            }

            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $duration = (int) ($data['duration_minutes'] ?? 60);
            $this->assertNoAssigneeConflict($assignee, $scheduledAt, $duration);

            $siteVisit = SiteVisit::create([
                'company_id' => $lead->company_id,
                'project_id' => $lead->project_id,
                'lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'scheduled_by_user_id' => $actor->id,
                'assigned_to_user_id' => $assignee->id,
                'visit_number' => $this->nextVisitNumber(),
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $duration,
                'visit_mode' => $data['visit_mode'],
                'meeting_location' => $data['meeting_location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'attendees' => $data['attendees'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('scheduled', $actor, 'Site visit scheduled'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $lead->forceFill([
                'stage' => 'Site Visit Scheduled',
                'follow_up_at' => $scheduledAt,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.site_visit.scheduled',
                'Scheduled CRM site visit',
                $siteVisit,
                ['lead_code' => $lead->lead_code, 'visit_number' => $siteVisit->visit_number, 'assigned_to' => $assignee->email],
                $request,
            );

            $this->campaignService->recordSystemActivity($lead, $actor, [
                'activity_type' => 'site_visit',
                'subject' => 'Site visit scheduled',
                'description' => $data['agenda'] ?? 'Site visit scheduled.',
                'new_stage' => $lead->stage,
                'outcome' => 'scheduled',
                'next_follow_up_at' => $scheduledAt,
                'metadata' => ['visit_number' => $siteVisit->visit_number, 'assigned_to' => $assignee->email],
            ]);

            if ($assignee->id !== $actor->id) {
                $this->notifications->sendToUser($assignee, [
                    'category' => 'crm',
                    'severity' => 'info',
                    'title' => "Site visit {$siteVisit->visit_number} assigned",
                    'body' => 'A site visit has been scheduled for '.$lead->customer->name.'.',
                    'action_url' => '/crm/site-visits?assigned_to_user_id='.$assignee->id,
                    'payload' => ['visit_number' => $siteVisit->visit_number, 'lead_code' => $lead->lead_code],
                ], $actor, $siteVisit);
            }

            return $siteVisit->load($this->siteVisitRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSiteVisit(SiteVisit $siteVisit, array $data, User $actor, ?Request $request = null): SiteVisit
    {
        return DB::transaction(function () use ($siteVisit, $data, $actor, $request): SiteVisit {
            $visit = SiteVisit::query()
                ->with(['lead.customer'])
                ->whereKey($siteVisit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($visit->status !== 'scheduled') {
                throw ValidationException::withMessages(['site_visit' => 'Only scheduled site visits can be updated.']);
            }

            if (! $this->companyScope->allows($actor, $visit->company_id)) {
                throw ValidationException::withMessages(['site_visit' => 'The selected site visit is not available for your company.']);
            }

            $assignee = isset($data['assigned_to_user_id'])
                ? User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail()
                : $actor;

            if ($assignee->company_id !== $visit->company_id) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The assigned user must belong to the site visit company.']);
            }

            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $duration = (int) ($data['duration_minutes'] ?? $visit->duration_minutes ?? 60);
            $this->assertNoAssigneeConflict($assignee, $scheduledAt, $duration, $visit->id);

            $oldSchedule = $visit->scheduled_at?->toISOString();
            $oldAssigneeId = $visit->assigned_to_user_id;
            $history = $visit->workflow_history ?? [];
            $history[] = $this->workflowEvent('rescheduled', $actor, 'Site visit schedule/details updated');

            $metadata = $visit->metadata ?? [];
            $metadata['last_update'] = [
                'updated_by_user_id' => $actor->id,
                'updated_by' => $actor->name,
                'old_scheduled_at' => $oldSchedule,
                'new_scheduled_at' => $scheduledAt->toISOString(),
                'old_assigned_to_user_id' => $oldAssigneeId,
                'new_assigned_to_user_id' => $assignee->id,
                'source' => $data['metadata']['source'] ?? 'site_visit_update',
                'updated_at' => now()->toISOString(),
            ];

            $visit->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => $duration,
                'visit_mode' => $data['visit_mode'],
                'meeting_location' => $data['meeting_location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'agenda' => $data['agenda'] ?? null,
                'attendees' => $data['attendees'] ?? [],
                'workflow_history' => $history,
                'metadata' => $metadata,
            ])->save();

            if ($visit->lead) {
                $visit->lead->forceFill([
                    'stage' => 'Site Visit Scheduled',
                    'follow_up_at' => $scheduledAt,
                ])->save();
            }

            $this->auditLogger->record(
                $actor,
                'crm.site_visit.updated',
                'Updated CRM site visit schedule/details',
                $visit,
                [
                    'visit_number' => $visit->visit_number,
                    'old_scheduled_at' => $oldSchedule,
                    'new_scheduled_at' => $scheduledAt->toISOString(),
                    'assigned_to' => $assignee->email,
                ],
                $request,
            );

            if ($visit->lead) {
                $this->campaignService->recordSystemActivity($visit->lead, $actor, [
                    'activity_type' => 'site_visit',
                    'subject' => 'Site visit updated',
                    'description' => $data['agenda'] ?? 'Site visit schedule/details updated.',
                    'new_stage' => $visit->lead->stage,
                    'outcome' => 'rescheduled',
                    'next_follow_up_at' => $scheduledAt,
                    'metadata' => ['visit_number' => $visit->visit_number, 'assigned_to' => $assignee->email],
                ]);
            }

            if ($assignee->id !== $actor->id && $assignee->id !== $oldAssigneeId) {
                $this->notifications->sendToUser($assignee, [
                    'category' => 'crm',
                    'severity' => 'info',
                    'title' => "Site visit {$visit->visit_number} reassigned",
                    'body' => 'A site visit has been assigned or rescheduled for you.',
                    'action_url' => '/crm/site-visits?assigned_to_user_id='.$assignee->id,
                    'payload' => ['visit_number' => $visit->visit_number],
                ], $actor, $visit);
            }

            return $visit->load($this->siteVisitRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function completeSiteVisit(SiteVisit $siteVisit, array $data, User $actor, ?Request $request = null): SiteVisit
    {
        return DB::transaction(function () use ($siteVisit, $data, $actor, $request): SiteVisit {
            $visit = SiteVisit::query()->with('lead')->whereKey($siteVisit->id)->lockForUpdate()->firstOrFail();

            if ($visit->status !== 'scheduled') {
                throw ValidationException::withMessages(['site_visit' => 'Only scheduled site visits can be completed.']);
            }

            $status = $data['outcome'] === 'no_show' ? 'no_show' : 'completed';
            $history = $visit->workflow_history ?? [];
            $history[] = $this->workflowEvent($status, $actor, $data['outcome_notes']);

            $visit->forceFill([
                'status' => $status,
                'outcome' => $data['outcome'],
                'outcome_notes' => $data['outcome_notes'],
                'completed_at' => now(),
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'workflow_history' => $history,
            ])->save();

            $visit->lead->forceFill([
                'stage' => match ($data['outcome']) {
                    'booking_expected' => 'Negotiation',
                    'not_interested', 'no_show' => 'Follow-up',
                    default => 'Site Visit Done',
                },
                'follow_up_at' => $data['next_follow_up_at'] ?? $visit->lead->follow_up_at,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.site_visit.completed',
                'Completed CRM site visit',
                $visit,
                ['visit_number' => $visit->visit_number, 'outcome' => $data['outcome']],
                $request,
            );

            $this->campaignService->recordSystemActivity($visit->lead, $actor, [
                'activity_type' => 'site_visit',
                'subject' => 'Site visit completed',
                'description' => $data['outcome_notes'],
                'new_stage' => $visit->lead->stage,
                'outcome' => $data['outcome'],
                'next_follow_up_at' => $visit->lead->follow_up_at,
                'metadata' => ['visit_number' => $visit->visit_number],
            ]);

            return $visit->load($this->siteVisitRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cancelSiteVisit(SiteVisit $siteVisit, array $data, User $actor, ?Request $request = null): SiteVisit
    {
        return DB::transaction(function () use ($siteVisit, $data, $actor, $request): SiteVisit {
            $visit = SiteVisit::query()->whereKey($siteVisit->id)->lockForUpdate()->firstOrFail();

            if ($visit->status !== 'scheduled') {
                throw ValidationException::withMessages(['site_visit' => 'Only scheduled site visits can be cancelled.']);
            }

            $history = $visit->workflow_history ?? [];
            $history[] = $this->workflowEvent('cancelled', $actor, $data['reason']);

            $visit->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.site_visit.cancelled',
                'Cancelled CRM site visit',
                $visit,
                ['visit_number' => $visit->visit_number, 'reason' => $data['reason']],
                $request,
            );

            $this->campaignService->recordSystemActivity($visit->lead, $actor, [
                'activity_type' => 'site_visit',
                'subject' => 'Site visit cancelled',
                'description' => $data['reason'],
                'new_stage' => $visit->lead->stage,
                'outcome' => 'cancelled',
                'metadata' => ['visit_number' => $visit->visit_number],
            ]);

            return $visit->load($this->siteVisitRelations());
        });
    }

    private function assertLeadCompany(Lead $lead, User $actor): void
    {
        if (! $this->companyScope->allows($actor, $lead->company_id)) {
            throw ValidationException::withMessages(['lead_id' => 'The selected lead is not available for your company.']);
        }
    }

    private function assertNoAssigneeConflict(User $assignee, Carbon $scheduledAt, int $durationMinutes, ?int $exceptSiteVisitId = null): void
    {
        $endsAt = $scheduledAt->copy()->addMinutes($durationMinutes);

        $conflictExists = SiteVisit::query()
            ->where('assigned_to_user_id', $assignee->id)
            ->where('status', 'scheduled')
            ->when($exceptSiteVisitId !== null, fn ($query) => $query->whereKeyNot($exceptSiteVisitId))
            ->get()
            ->contains(function (SiteVisit $visit) use ($scheduledAt, $endsAt): bool {
                $visitStart = $visit->scheduled_at;
                $visitEnd = $visit->scheduled_at->copy()->addMinutes($visit->duration_minutes);

                return $visitStart < $endsAt && $visitEnd > $scheduledAt;
            });

        if ($conflictExists) {
            throw ValidationException::withMessages(['scheduled_at' => 'The assigned user already has a site visit in this time window.']);
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

    private function nextQualificationNumber(): string
    {
        return sprintf('LQ-%05d', LeadQualification::query()->withTrashed()->count() + 10001);
    }

    private function nextVisitNumber(): string
    {
        return sprintf('SV-%05d', SiteVisit::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function qualificationRelations(): array
    {
        return ['lead.customer', 'qualifiedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function siteVisitRelations(): array
    {
        return ['lead', 'customer', 'project', 'scheduledBy', 'assignedTo'];
    }
}
