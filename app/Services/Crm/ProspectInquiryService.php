<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\Project;
use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectInquiryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly LeadService $leadService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * @param array<string, mixed> $metadata
     */
    public function capturePublic(array $data, ?Request $request = null, ?User $actor = null, array $metadata = []): ProspectInquiry
    {
        return DB::transaction(function () use ($data, $request, $actor, $metadata): ProspectInquiry {
            /** @var Project $project */
            $project = Project::query()->findOrFail($data['project_id']);

            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;
            $duplicate = $this->findDuplicate($project->id, $email, $phone);

            $inquiry = ProspectInquiry::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'duplicate_of_id' => $duplicate?->id,
                'inquiry_number' => $this->nextInquiryNumber(),
                'name' => trim((string) $data['name']),
                'email' => $email,
                'phone' => $phone,
                'source' => $data['source'] ?? 'Website',
                'channel' => $data['channel'] ?? 'website',
                'preferred_contact_method' => $data['preferred_contact_method'] ?? null,
                'status' => $duplicate ? ProspectInquiry::STATUS_DUPLICATE : ProspectInquiry::STATUS_NEW,
                'budget_min' => $data['budget_min'] ?? null,
                'budget_max' => $data['budget_max'] ?? null,
                'message' => $data['message'] ?? null,
                'consent_to_contact' => (bool) ($data['consent_to_contact'] ?? false),
                'utm_source' => $data['utm_source'] ?? null,
                'utm_medium' => $data['utm_medium'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
                'metadata' => [
                    'capture_context' => [
                        'ip' => $request?->ip(),
                        'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 255) : null,
                    ],
                    'duplicate_detected' => (bool) $duplicate,
                    'duplicate_inquiry_number' => $duplicate?->inquiry_number,
                ] + $metadata,
            ]);

            $this->auditLogger->record(
                $actor,
                'crm.prospect_inquiry.captured',
                $actor ? 'Captured prospect inquiry from import' : 'Captured public prospect inquiry',
                $inquiry,
                [
                    'inquiry_number' => $inquiry->inquiry_number,
                    'project_id' => $project->id,
                    'company_id' => $project->company_id,
                    'status' => $inquiry->status,
                    'duplicate_of' => $duplicate?->inquiry_number,
                    'import_number' => $metadata['import_number'] ?? null,
                    'row_number' => $metadata['row_number'] ?? null,
                ],
                $request,
            );

            $this->notifications->sendToPermission(
                ['crm.manage'],
                [
                    'category' => 'crm',
                    'severity' => $duplicate ? 'warning' : 'info',
                    'title' => $duplicate ? 'Duplicate prospect inquiry received' : 'New prospect inquiry received',
                    'body' => $inquiry->name.' submitted an inquiry for '.$project->name.'.',
                    'action_url' => route('crm.prospect-inquiries.index'),
                    'payload' => [
                        'inquiry_number' => $inquiry->inquiry_number,
                        'project_id' => $project->id,
                        'status' => $inquiry->status,
                    ],
                ],
                null,
                $inquiry,
                (int) $project->company_id,
            );

            return $inquiry->load(['company', 'project', 'assignedTo', 'convertedLead', 'duplicateOf']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assign(ProspectInquiry $inquiry, array $data, User $actor, ?Request $request = null): ProspectInquiry
    {
        return DB::transaction(function () use ($inquiry, $data, $actor, $request): ProspectInquiry {
            $this->ensureOpenForAction($inquiry);

            $assignee = User::query()->findOrFail($data['assigned_to_user_id']);
            $before = [
                'status' => $inquiry->status,
                'assigned_to_user_id' => $inquiry->assigned_to_user_id,
            ];

            $inquiry->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'status' => ProspectInquiry::STATUS_ASSIGNED,
                'assigned_at' => now(),
                'metadata' => array_merge($inquiry->metadata ?? [], [
                    'assignment_note' => $data['note'] ?? null,
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.prospect_inquiry.assigned',
                'Assigned prospect inquiry',
                $inquiry,
                [
                    'inquiry_number' => $inquiry->inquiry_number,
                    'before' => $before,
                    'after' => [
                        'status' => $inquiry->status,
                        'assigned_to_user_id' => $assignee->id,
                    ],
                ],
                $request,
            );

            $this->notifications->sendToUser($assignee, [
                'category' => 'crm',
                'severity' => 'info',
                'title' => 'Prospect inquiry assigned',
                'body' => $actor->name.' assigned '.$inquiry->inquiry_number.' to you.',
                'action_url' => route('crm.prospect-inquiries.index', ['assigned_to_user_id' => $assignee->id]),
                'payload' => ['inquiry_number' => $inquiry->inquiry_number],
            ], $actor, $inquiry);

            return $inquiry->refresh()->load(['company', 'project', 'assignedTo', 'convertedLead', 'duplicateOf']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function convertToLead(ProspectInquiry $inquiry, array $data, User $actor, ?Request $request = null): ProspectInquiry
    {
        return DB::transaction(function () use ($inquiry, $data, $actor, $request): ProspectInquiry {
            $this->ensureOpenForAction($inquiry);

            if ($inquiry->status === ProspectInquiry::STATUS_DUPLICATE) {
                throw ValidationException::withMessages([
                    'status' => 'Duplicate inquiries must be reviewed and assigned before conversion.',
                ]);
            }

            $expectedValue = $data['expected_value']
                ?? $inquiry->budget_max
                ?? $inquiry->budget_min
                ?? 0;

            /** @var Lead $lead */
            $lead = $this->leadService->create([
                'company_id' => $inquiry->company_id,
                'project_id' => $inquiry->project_id,
                'marketing_campaign_id' => $data['marketing_campaign_id'] ?? null,
                'customer_name' => $inquiry->name,
                'customer_email' => $inquiry->email,
                'customer_phone' => $inquiry->phone,
                'source' => $inquiry->source,
                'stage' => $data['stage'] ?? 'New',
                'status' => 'open',
                'budget_min' => $inquiry->budget_min,
                'budget_max' => $inquiry->budget_max,
                'expected_value' => $expectedValue,
                'follow_up_at' => $data['follow_up_at'] ?? null,
            ], $actor, $request);

            $inquiry->forceFill([
                'converted_lead_id' => $lead->id,
                'status' => ProspectInquiry::STATUS_CONVERTED,
                'converted_at' => now(),
                'closed_at' => now(),
                'metadata' => array_merge($inquiry->metadata ?? [], [
                    'conversion_note' => $data['note'] ?? null,
                    'converted_lead_code' => $lead->lead_code,
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.prospect_inquiry.converted',
                'Converted prospect inquiry to lead',
                $inquiry,
                [
                    'inquiry_number' => $inquiry->inquiry_number,
                    'lead_code' => $lead->lead_code,
                    'lead_id' => $lead->id,
                ],
                $request,
            );

            return $inquiry->refresh()->load(['company', 'project', 'assignedTo', 'convertedLead', 'duplicateOf']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function close(ProspectInquiry $inquiry, array $data, User $actor, ?Request $request = null): ProspectInquiry
    {
        return DB::transaction(function () use ($inquiry, $data, $actor, $request): ProspectInquiry {
            $this->ensureOpenForAction($inquiry);

            $beforeStatus = $inquiry->status;

            $inquiry->forceFill([
                'status' => $data['status'],
                'closed_at' => now(),
                'metadata' => array_merge($inquiry->metadata ?? [], [
                    'closure_reason' => $data['reason'],
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'crm.prospect_inquiry.closed',
                'Closed prospect inquiry',
                $inquiry,
                [
                    'inquiry_number' => $inquiry->inquiry_number,
                    'before_status' => $beforeStatus,
                    'after_status' => $inquiry->status,
                    'reason' => $data['reason'],
                ],
                $request,
            );

            return $inquiry->refresh()->load(['company', 'project', 'assignedTo', 'convertedLead', 'duplicateOf']);
        });
    }

    private function findDuplicate(int $projectId, ?string $email, ?string $phone): ?ProspectInquiry
    {
        if ($email === null && $phone === null) {
            return null;
        }

        return ProspectInquiry::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ProspectInquiry::OPEN_STATUSES)
            ->where(function ($query) use ($email, $phone): void {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }

                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
            })
            ->latest()
            ->first();
    }

    private function ensureOpenForAction(ProspectInquiry $inquiry): void
    {
        if ($inquiry->isClosed()) {
            throw ValidationException::withMessages([
                'status' => 'Closed or converted inquiries cannot be changed.',
            ]);
        }
    }

    private function nextInquiryNumber(): string
    {
        return sprintf('PI-%05d', ProspectInquiry::query()->withTrashed()->count() + 10001);
    }
}
