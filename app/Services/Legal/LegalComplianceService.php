<?php

namespace App\Services\Legal;

use App\Models\ComplianceObligation;
use App\Models\ProjectApproval;
use App\Models\Project;
use App\Models\ReraRegistration;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalComplianceService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createReraRegistration(array $data, User $actor, ?Request $request = null): ReraRegistration
    {
        return DB::transaction(function () use ($data, $actor, $request): ReraRegistration {
            $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $project->company_id)) {
                throw ValidationException::withMessages(['project_id' => 'The selected project is not active for your company.']);
            }

            $registration = ReraRegistration::create([
                'company_id' => $project->company_id,
                'project_id' => $data['project_id'],
                'created_by_user_id' => $actor->id,
                'registration_number' => $data['registration_number'],
                'authority_name' => $data['authority_name'],
                'state_code' => strtoupper($data['state_code']),
                'registered_on' => $data['registered_on'],
                'expires_on' => $data['expires_on'] ?? null,
                'status' => 'submitted',
                'document_reference' => $data['document_reference'] ?? null,
                'conditions' => $data['conditions'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'RERA registration submitted'),
                ],
                'metadata' => $data['metadata'] ?? ['source' => 'legal_compliance_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'legal.rera.submitted',
                'Submitted RERA registration',
                $registration,
                ['registration_number' => $registration->registration_number, 'project_id' => $registration->project_id],
                $request,
            );

            return $registration->load($this->reraRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function verifyReraRegistration(ReraRegistration $reraRegistration, array $data, User $actor, ?Request $request = null): ReraRegistration
    {
        return DB::transaction(function () use ($reraRegistration, $data, $actor, $request): ReraRegistration {
            $registration = ReraRegistration::query()->whereKey($reraRegistration->id)->lockForUpdate()->firstOrFail();

            if ($registration->status !== 'submitted') {
                throw ValidationException::withMessages(['rera_registration' => 'Only submitted RERA registrations can be verified.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $registration->company_id)) {
                throw ValidationException::withMessages(['rera_registration' => 'The selected RERA registration is outside your company scope.']);
            }

            if ($registration->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['rera_registration' => 'The submitter cannot verify the same RERA registration.']);
            }

            $history = $registration->workflow_history ?? [];
            $history[] = $this->workflowEvent('verified', $actor, $data['verification_note'] ?? 'RERA registration verified');

            $registration->forceFill([
                'status' => 'verified',
                'verified_by_user_id' => $actor->id,
                'verified_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'legal.rera.verified',
                'Verified RERA registration',
                $registration,
                [
                    'registration_number' => $registration->registration_number,
                    'verification_note' => $data['verification_note'] ?? null,
                ],
                $request,
            );

            return $registration->load($this->reraRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createProjectApproval(array $data, User $actor, ?Request $request = null): ProjectApproval
    {
        return DB::transaction(function () use ($data, $actor, $request): ProjectApproval {
            $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $project->company_id)) {
                throw ValidationException::withMessages(['project_id' => 'The selected project is not active for your company.']);
            }

            $approval = ProjectApproval::create([
                'company_id' => $project->company_id,
                'project_id' => $data['project_id'],
                'responsible_user_id' => $actor->id,
                'approval_code' => $data['approval_code'],
                'approval_type' => $data['approval_type'],
                'authority_name' => $data['authority_name'],
                'application_number' => $data['application_number'] ?? null,
                'applied_on' => $data['applied_on'] ?? null,
                'approved_on' => $data['approved_on'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'status' => $data['status'],
                'required_for' => $data['required_for'] ?? null,
                'document_reference' => $data['document_reference'] ?? null,
                'conditions' => $data['conditions'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent($data['status'], $actor, 'Project approval recorded'),
                ],
                'metadata' => $data['metadata'] ?? ['source' => 'legal_compliance_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'legal.project_approval.created',
                'Created project approval record',
                $approval,
                ['approval_code' => $approval->approval_code, 'status' => $approval->status],
                $request,
            );

            return $approval->load($this->approvalRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function verifyProjectApproval(ProjectApproval $projectApproval, array $data, User $actor, ?Request $request = null): ProjectApproval
    {
        return DB::transaction(function () use ($projectApproval, $data, $actor, $request): ProjectApproval {
            $approval = ProjectApproval::query()->whereKey($projectApproval->id)->lockForUpdate()->firstOrFail();

            if (! in_array($approval->status, ['applied', 'approved'], true)) {
                throw ValidationException::withMessages(['project_approval' => 'Only active project approvals can be verified.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $approval->company_id)) {
                throw ValidationException::withMessages(['project_approval' => 'The selected project approval is outside your company scope.']);
            }

            if ($approval->responsible_user_id === $actor->id) {
                throw ValidationException::withMessages(['project_approval' => 'The responsible user cannot verify the same project approval.']);
            }

            $history = $approval->workflow_history ?? [];
            $history[] = $this->workflowEvent('verified', $actor, $data['verification_note'] ?? 'Project approval verified');

            $approval->forceFill([
                'status' => 'verified',
                'verified_by_user_id' => $actor->id,
                'verified_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'legal.project_approval.verified',
                'Verified project approval',
                $approval,
                [
                    'approval_code' => $approval->approval_code,
                    'verification_note' => $data['verification_note'] ?? null,
                ],
                $request,
            );

            return $approval->load($this->approvalRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createComplianceObligation(array $data, User $actor, ?Request $request = null): ComplianceObligation
    {
        return DB::transaction(function () use ($data, $actor, $request): ComplianceObligation {
            $companyId = app(CompanyScopeService::class)->companyIdFor($actor);

            if (isset($data['project_id'])) {
                $project = Project::query()->whereKey($data['project_id'])->firstOrFail();

                if (! app(CompanyScopeService::class)->allows($actor, $project->company_id)) {
                    throw ValidationException::withMessages(['project_id' => 'The selected project is not active for your company.']);
                }

                $companyId = (int) $project->company_id;
            }

            if ($companyId === null || $companyId <= 0) {
                throw ValidationException::withMessages(['project_id' => 'Compliance obligations require a valid company scope.']);
            }

            $obligation = ComplianceObligation::create([
                'company_id' => $companyId,
                'project_id' => $data['project_id'] ?? null,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $actor->id,
                'obligation_number' => $this->nextObligationNumber(),
                'title' => $data['title'],
                'compliance_type' => $data['compliance_type'],
                'due_on' => $data['due_on'],
                'frequency' => $data['frequency'],
                'priority' => $data['priority'],
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('open', $actor, 'Compliance obligation created'),
                ],
                'metadata' => $data['metadata'] ?? ['source' => 'legal_compliance_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'legal.compliance_obligation.created',
                'Created compliance obligation',
                $obligation,
                ['obligation_number' => $obligation->obligation_number, 'due_on' => $obligation->due_on?->toDateString()],
                $request,
            );

            return $obligation->load($this->obligationRelations());
        });
    }

    public function completeComplianceObligation(ComplianceObligation $complianceObligation, array $data, User $actor, ?Request $request = null): ComplianceObligation
    {
        return DB::transaction(function () use ($complianceObligation, $data, $actor, $request): ComplianceObligation {
            $obligation = ComplianceObligation::query()->whereKey($complianceObligation->id)->lockForUpdate()->firstOrFail();

            if ($obligation->status !== 'open') {
                throw ValidationException::withMessages(['compliance_obligation' => 'Only open compliance obligations can be completed.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $obligation->company_id)) {
                throw ValidationException::withMessages(['compliance_obligation' => 'The selected compliance obligation is outside your company scope.']);
            }

            $history = $obligation->workflow_history ?? [];
            $history[] = $this->workflowEvent('completed', $actor, $data['notes'] ?? 'Compliance obligation completed');

            $obligation->forceFill([
                'status' => 'completed',
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
                'evidence_document_reference' => $data['evidence_document_reference'],
                'notes' => $data['notes'] ?? $obligation->notes,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'legal.compliance_obligation.completed',
                'Completed compliance obligation',
                $obligation,
                ['obligation_number' => $obligation->obligation_number],
                $request,
            );

            return $obligation->load($this->obligationRelations());
        });
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextObligationNumber(): string
    {
        return sprintf('COMP-%04d', ComplianceObligation::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, string>
     */
    private function reraRelations(): array
    {
        return ['project', 'createdBy', 'verifiedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function approvalRelations(): array
    {
        return ['project', 'responsibleUser', 'verifiedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function obligationRelations(): array
    {
        return ['project', 'assignedTo', 'completedBy'];
    }
}
