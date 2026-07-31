<?php

namespace App\Services\Maintenance;

use App\Models\Booking;
use App\Models\CommonAreaHandoverItem;
use App\Models\MaintenanceDue;
use App\Models\Project;
use App\Models\SocietyFormation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceSocietyService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSocietyFormation(array $data, User $actor, ?Request $request = null): SocietyFormation
    {
        return DB::transaction(function () use ($data, $actor, $request): SocietyFormation {
            $project = Project::query()->whereKey($data['project_id'])->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $project->company_id, 'project_id');

            $status = $data['status'] ?? 'draft';
            $progress = (int) ($data['progress_percent'] ?? $this->progressForSocietyStatus($status));

            $formation = SocietyFormation::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'formation_number' => $this->nextSocietyFormationNumber(),
                'society_name' => $data['society_name'],
                'association_type' => $data['association_type'] ?? 'cooperative_society',
                'total_units' => $data['total_units'],
                'occupied_units' => $data['occupied_units'] ?? 0,
                'registration_number' => $data['registration_number'] ?? null,
                'application_filed_on' => $data['application_filed_on'] ?? null,
                'registered_on' => $data['registered_on'] ?? null,
                'target_handover_on' => $data['target_handover_on'] ?? null,
                'status' => $status,
                'progress_percent' => $progress,
                'current_stage' => $data['current_stage'] ?? $this->labelForSocietyStatus($status),
                'next_step' => $data['next_step'] ?? null,
                'committee_members' => $data['committee_members'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent($status, $actor, 'Society formation created'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->auditLogger->record(
                $actor,
                'maintenance.society_formation.created',
                'Created society formation record',
                $formation,
                ['formation_number' => $formation->formation_number, 'project_code' => $project->code],
                $request,
            );

            return $formation->load($this->societyRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSocietyFormationStatus(SocietyFormation $societyFormation, array $data, User $actor, ?Request $request = null): SocietyFormation
    {
        return DB::transaction(function () use ($societyFormation, $data, $actor, $request): SocietyFormation {
            $formation = SocietyFormation::query()->whereKey($societyFormation->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $formation->company_id, 'society_formation');

            $history = $formation->workflow_history ?? [];
            $history[] = $this->workflowEvent($data['status'], $actor, $data['note'] ?? 'Society formation status updated');

            $formation->forceFill([
                'updated_by_user_id' => $actor->id,
                'status' => $data['status'],
                'progress_percent' => $data['progress_percent'],
                'current_stage' => $data['current_stage'] ?? $this->labelForSocietyStatus($data['status']),
                'next_step' => $data['next_step'] ?? $formation->next_step,
                'registration_number' => $data['registration_number'] ?? $formation->registration_number,
                'registered_on' => $data['status'] === 'formed' && $formation->registered_on === null ? now()->toDateString() : $formation->registered_on,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'maintenance.society_formation.status_updated',
                'Updated society formation status',
                $formation,
                ['formation_number' => $formation->formation_number, 'status' => $formation->status],
                $request,
            );

            return $formation->load($this->societyRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCommonAreaHandoverItem(CommonAreaHandoverItem $commonAreaHandoverItem, array $data, User $actor, ?Request $request = null): CommonAreaHandoverItem
    {
        return DB::transaction(function () use ($commonAreaHandoverItem, $data, $actor, $request): CommonAreaHandoverItem {
            $item = CommonAreaHandoverItem::query()->whereKey($commonAreaHandoverItem->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $item->company_id, 'common_area_handover_item');

            $history = $item->workflow_history ?? [];
            $history[] = $this->workflowEvent($data['status'], $actor, $data['note'] ?? 'Common-area checklist updated');

            $item->forceFill([
                'checklist_completed' => $data['checklist_completed'],
                'status' => $data['status'],
                'snag_summary' => $data['snag_summary'] ?? $item->snag_summary,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'maintenance.common_area_handover.updated',
                'Updated common-area handover checklist',
                $item,
                ['item_number' => $item->item_number, 'status' => $item->status],
                $request,
            );

            return $item->load($this->handoverItemRelations());
        });
    }

    public function signOffCommonAreaHandoverItem(CommonAreaHandoverItem $commonAreaHandoverItem, array $data, User $actor, ?Request $request = null): CommonAreaHandoverItem
    {
        return DB::transaction(function () use ($commonAreaHandoverItem, $data, $actor, $request): CommonAreaHandoverItem {
            $item = CommonAreaHandoverItem::query()->whereKey($commonAreaHandoverItem->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $item->company_id, 'common_area_handover_item');

            if ((int) $item->checklist_completed < (int) $item->checklist_total) {
                throw ValidationException::withMessages(['checklist_completed' => 'All checklist items must be complete before sign-off.']);
            }

            $history = $item->workflow_history ?? [];
            $history[] = $this->workflowEvent('complete', $actor, $data['note'] ?? 'Common-area handover signed off');

            $item->forceFill([
                'status' => 'complete',
                'signed_off_by_user_id' => $actor->id,
                'signed_off_on' => now()->toDateString(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'maintenance.common_area_handover.signed_off',
                'Signed off common-area handover item',
                $item,
                ['item_number' => $item->item_number],
                $request,
            );

            return $item->load($this->handoverItemRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function raiseMaintenanceDue(array $data, User $actor, ?Request $request = null): MaintenanceDue
    {
        return DB::transaction(function () use ($data, $actor, $request): MaintenanceDue {
            $booking = Booking::query()
                ->with(['customer', 'unit', 'project'])
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $booking->company_id, 'booking_id');

            $amount = round((float) $data['amount'], 2);
            $due = MaintenanceDue::create([
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'raised_by_user_id' => $actor->id,
                'due_number' => $this->nextMaintenanceDueNumber(),
                'period_start_on' => $data['period_start_on'],
                'period_end_on' => $data['period_end_on'],
                'due_on' => $data['due_on'],
                'amount' => $amount,
                'paid_amount' => 0,
                'balance_amount' => $amount,
                'status' => now()->toDateString() > $data['due_on'] ? 'overdue' : 'due',
                'workflow_history' => [
                    $this->workflowEvent('due', $actor, 'Maintenance due raised'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->auditLogger->record(
                $actor,
                'maintenance.due.raised',
                'Raised maintenance due',
                $due,
                ['due_number' => $due->due_number, 'booking_code' => $booking->booking_code, 'amount' => $amount],
                $request,
            );

            $this->notifyBuyerIfAvailable($due, $actor, 'Maintenance due raised', 'A maintenance demand has been raised for your unit.');

            return $due->load($this->maintenanceDueRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function markMaintenanceDuePaid(MaintenanceDue $maintenanceDue, array $data, User $actor, ?Request $request = null): MaintenanceDue
    {
        return DB::transaction(function () use ($maintenanceDue, $data, $actor, $request): MaintenanceDue {
            $due = MaintenanceDue::query()->whereKey($maintenanceDue->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $due->company_id, 'maintenance_due');

            $paidAmount = round((float) $data['paid_amount'], 2);
            $newPaid = round((float) $due->paid_amount + $paidAmount, 2);
            $newBalance = round((float) $due->amount - $newPaid, 2);
            if ($newBalance < 0) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot exceed the outstanding maintenance balance.']);
            }

            $history = $due->workflow_history ?? [];
            $history[] = $this->workflowEvent($newBalance <= 0 ? 'paid' : 'part_payment', $actor, $data['note'] ?? 'Maintenance payment recorded');

            $due->forceFill([
                'paid_by_user_id' => $actor->id,
                'paid_amount' => $newPaid,
                'balance_amount' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : ($due->due_on?->isPast() ? 'overdue' : 'due'),
                'paid_at' => $newBalance <= 0 ? ($data['paid_at'] ?? now()) : $due->paid_at,
                'payment_reference' => $data['payment_reference'],
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'maintenance.due.payment_recorded',
                'Recorded maintenance due payment',
                $due,
                ['due_number' => $due->due_number, 'paid_amount' => $paidAmount, 'balance_amount' => $newBalance],
                $request,
            );

            return $due->load($this->maintenanceDueRelations());
        });
    }

    public function remindMaintenanceDue(MaintenanceDue $maintenanceDue, array $data, User $actor, ?Request $request = null): MaintenanceDue
    {
        return DB::transaction(function () use ($maintenanceDue, $data, $actor, $request): MaintenanceDue {
            $due = MaintenanceDue::query()->whereKey($maintenanceDue->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $due->company_id, 'maintenance_due');

            $history = $due->workflow_history ?? [];
            $history[] = $this->workflowEvent('reminded', $actor, $data['note'] ?? 'Maintenance due reminder sent');

            $due->forceFill([
                'last_reminded_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'maintenance.due.reminder_sent',
                'Sent maintenance due reminder',
                $due,
                ['due_number' => $due->due_number],
                $request,
            );

            $this->notifyBuyerIfAvailable($due, $actor, 'Maintenance due reminder', 'A reminder has been sent for your outstanding maintenance demand.');

            return $due->load($this->maintenanceDueRelations());
        });
    }

    /**
     * @return array<int, string>
     */
    public function societyRelations(): array
    {
        return ['project', 'createdBy', 'updatedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function handoverItemRelations(): array
    {
        return ['project', 'societyFormation', 'responsibleUser', 'signedOffBy'];
    }

    /**
     * @return array<int, string>
     */
    public function maintenanceDueRelations(): array
    {
        return ['project', 'booking', 'customer', 'unit', 'raisedBy', 'paidBy'];
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

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }

    private function nextSocietyFormationNumber(): string
    {
        return sprintf('SOC-%04d', SocietyFormation::query()->withTrashed()->count() + 1001);
    }

    private function nextMaintenanceDueNumber(): string
    {
        return sprintf('MDU-%04d', MaintenanceDue::query()->withTrashed()->count() + 1001);
    }

    private function progressForSocietyStatus(string $status): int
    {
        return match ($status) {
            'application_filed' => 35,
            'in_progress' => 65,
            'formed' => 90,
            'handed_over' => 100,
            'blocked' => 50,
            default => 10,
        };
    }

    private function labelForSocietyStatus(string $status): string
    {
        return match ($status) {
            'application_filed' => 'Application filed',
            'in_progress' => 'Formation in progress',
            'formed' => 'Registered',
            'handed_over' => 'Handed over',
            'blocked' => 'Blocked',
            default => 'Draft',
        };
    }

    private function notifyBuyerIfAvailable(MaintenanceDue $due, User $actor, string $title, string $body): void
    {
        $due->loadMissing('customer');
        $buyer = $due->customer?->portalUser;

        if (! $buyer) {
            return;
        }

        $this->notifications->sendToUser($buyer, [
            'category' => 'maintenance',
            'severity' => $due->status === 'overdue' ? 'warning' : 'info',
            'title' => $title,
            'body' => $body,
            'action_url' => '/buyer/portal',
            'payload' => [
                'due_number' => $due->due_number,
                'balance_amount' => (float) $due->balance_amount,
            ],
        ], $actor, $due);
    }
}
