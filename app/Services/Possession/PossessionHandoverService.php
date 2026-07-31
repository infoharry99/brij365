<?php

namespace App\Services\Possession;

use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\HandoverSnag;
use App\Models\PossessionHandover;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PossessionHandoverService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function initiate(array $data, User $actor, ?Request $request = null): PossessionHandover
    {
        return DB::transaction(function () use ($data, $actor, $request): PossessionHandover {
            $booking = Booking::query()
                ->with(['project', 'unit', 'customer'])
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $booking->company_id, 'booking_id');

            $outstanding = $this->financialOutstanding($booking);
            $checklist = $data['checklist'] ?? $this->defaultChecklist();
            $blockers = $this->blockers($outstanding, $checklist, 0);

            $handover = PossessionHandover::create([
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'initiated_by_user_id' => $actor->id,
                'handover_number' => $this->nextHandoverNumber(),
                'target_handover_on' => $data['target_handover_on'] ?? null,
                'status' => empty($blockers) ? 'ready' : 'blocked',
                'financial_outstanding' => $outstanding,
                'checklist' => $checklist,
                'blockers' => $blockers,
                'workflow_history' => [
                    $this->workflowEvent('initiated', $actor, 'Possession handover initiated'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'possession.handover.initiated',
                'Initiated possession handover',
                $handover,
                ['handover_number' => $handover->handover_number, 'booking_code' => $booking->booking_code, 'status' => $handover->status],
                $request,
            );

            return $handover->load($this->handoverRelations());
        });
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     */
    public function updateChecklist(PossessionHandover $possessionHandover, array $checklist, User $actor, ?Request $request = null): PossessionHandover
    {
        return DB::transaction(function () use ($possessionHandover, $checklist, $actor, $request): PossessionHandover {
            $handover = PossessionHandover::query()->whereKey($possessionHandover->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $handover->company_id, 'handover');

            if ($handover->status === 'completed') {
                throw ValidationException::withMessages(['handover' => 'Completed handovers cannot be modified.']);
            }

            $outstanding = $this->financialOutstanding($handover->booking()->firstOrFail());
            $openSnags = $this->openSnagCount($handover);
            $blockers = $this->blockers($outstanding, $checklist, $openSnags);
            $history = $handover->workflow_history ?? [];
            $history[] = $this->workflowEvent('checklist_updated', $actor, 'Handover checklist updated');

            $handover->forceFill([
                'financial_outstanding' => $outstanding,
                'checklist' => $checklist,
                'blockers' => $blockers,
                'status' => empty($blockers) ? 'ready' : 'blocked',
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'possession.handover.checklist_updated',
                'Updated possession handover checklist',
                $handover,
                ['handover_number' => $handover->handover_number, 'status' => $handover->status],
                $request,
            );

            return $handover->load($this->handoverRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function reportSnag(array $data, User $actor, ?Request $request = null): HandoverSnag
    {
        return DB::transaction(function () use ($data, $actor, $request): HandoverSnag {
            $handover = PossessionHandover::query()->whereKey($data['possession_handover_id'])->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $handover->company_id, 'possession_handover_id');

            $snag = HandoverSnag::create([
                'company_id' => $handover->company_id,
                'possession_handover_id' => $handover->id,
                'reported_by_user_id' => $actor->id,
                'snag_number' => $this->nextSnagNumber(),
                'area' => $data['area'],
                'category' => $data['category'],
                'severity' => $data['severity'],
                'description' => $data['description'],
                'status' => 'open',
                'target_resolution_on' => $data['target_resolution_on'] ?? null,
                'attachments' => $data['attachments'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('open', $actor, 'Handover snag reported'),
                ],
            ]);

            $this->refreshReadiness($handover, $actor, 'Snag reported');

            $this->auditLogger->record(
                $actor,
                'possession.snag.reported',
                'Reported handover snag',
                $snag,
                ['snag_number' => $snag->snag_number, 'handover_number' => $handover->handover_number],
                $request,
            );

            return $snag->load($this->snagRelations());
        });
    }

    public function resolveSnag(HandoverSnag $handoverSnag, string $resolutionNotes, User $actor, ?Request $request = null): HandoverSnag
    {
        return DB::transaction(function () use ($handoverSnag, $resolutionNotes, $actor, $request): HandoverSnag {
            $snag = HandoverSnag::query()->whereKey($handoverSnag->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $snag->company_id, 'snag');

            if ($snag->status !== 'open') {
                throw ValidationException::withMessages(['snag' => 'Only open snags can be resolved.']);
            }

            $history = $snag->workflow_history ?? [];
            $history[] = $this->workflowEvent('resolved', $actor, $resolutionNotes);

            $snag->forceFill([
                'status' => 'resolved',
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'resolution_notes' => $resolutionNotes,
                'workflow_history' => $history,
            ])->save();

            $handover = $snag->handover()->lockForUpdate()->firstOrFail();
            $this->refreshReadiness($handover, $actor, 'Snag resolved');

            $this->auditLogger->record(
                $actor,
                'possession.snag.resolved',
                'Resolved handover snag',
                $snag,
                ['snag_number' => $snag->snag_number, 'handover_number' => $handover->handover_number],
                $request,
            );

            return $snag->load($this->snagRelations());
        });
    }

    public function issueLetter(PossessionHandover $possessionHandover, string $letterReference, User $actor, ?Request $request = null): PossessionHandover
    {
        return DB::transaction(function () use ($possessionHandover, $letterReference, $actor, $request): PossessionHandover {
            $handover = PossessionHandover::query()->whereKey($possessionHandover->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $handover->company_id, 'handover');

            if ($handover->status === 'completed') {
                throw ValidationException::withMessages(['handover' => 'Possession letters cannot be issued for completed handovers.']);
            }

            $outstanding = $this->financialOutstanding($handover->booking()->firstOrFail());
            $openSnags = $this->openSnagCount($handover);
            $blockers = $this->blockers($outstanding, $handover->checklist ?? [], $openSnags);

            if (! empty($blockers)) {
                $handover->forceFill([
                    'status' => 'blocked',
                    'financial_outstanding' => $outstanding,
                    'blockers' => $blockers,
                ])->save();

                throw ValidationException::withMessages(['handover' => 'Possession letter cannot be issued until payment, checklist and snag blockers are cleared.']);
            }

            $history = $handover->workflow_history ?? [];
            $history[] = $this->workflowEvent('letter_issued', $actor, 'Possession letter issued with reference '.$letterReference);

            $handover->forceFill([
                'status' => 'ready',
                'financial_outstanding' => 0,
                'blockers' => [],
                'possession_letter_reference' => $letterReference,
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'possession.handover.letter_issued',
                'Issued possession letter',
                $handover,
                ['handover_number' => $handover->handover_number, 'letter_reference' => $letterReference],
                $request,
            );

            return $handover->load($this->handoverRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function complete(PossessionHandover $possessionHandover, array $data, User $actor, ?Request $request = null): PossessionHandover
    {
        return DB::transaction(function () use ($possessionHandover, $data, $actor, $request): PossessionHandover {
            $handover = PossessionHandover::query()->whereKey($possessionHandover->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $handover->company_id, 'handover');

            $outstanding = $this->financialOutstanding($handover->booking()->firstOrFail());
            $openSnags = $this->openSnagCount($handover);
            $blockers = $this->blockers($outstanding, $handover->checklist ?? [], $openSnags);

            if (! empty($blockers)) {
                $handover->forceFill([
                    'status' => 'blocked',
                    'financial_outstanding' => $outstanding,
                    'blockers' => $blockers,
                ])->save();

                throw ValidationException::withMessages(['handover' => 'Handover cannot be completed until all blockers are cleared.']);
            }

            if (! $handover->possession_letter_reference) {
                throw ValidationException::withMessages(['possession_letter_reference' => 'Possession letter must be issued before completion.']);
            }

            if ($data['possession_letter_reference'] !== $handover->possession_letter_reference) {
                throw ValidationException::withMessages(['possession_letter_reference' => 'The completion reference must match the issued possession letter.']);
            }

            $history = $handover->workflow_history ?? [];
            $history[] = $this->workflowEvent('completed', $actor, 'Possession handover completed');

            $handover->forceFill([
                'status' => 'completed',
                'financial_outstanding' => 0,
                'blockers' => [],
                'actual_handover_on' => $data['actual_handover_on'],
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
                'workflow_history' => $history,
            ])->save();

            ProjectUnit::whereKey($handover->project_unit_id)->update(['status' => 'handed_over']);

            $this->auditLogger->record(
                $actor,
                'possession.handover.completed',
                'Completed possession handover',
                $handover,
                ['handover_number' => $handover->handover_number, 'letter_reference' => $handover->possession_letter_reference],
                $request,
            );

            return $handover->load($this->handoverRelations());
        });
    }

    private function refreshReadiness(PossessionHandover $handover, User $actor, string $note): void
    {
        if ($handover->status === 'completed') {
            return;
        }

        $outstanding = $this->financialOutstanding($handover->booking()->firstOrFail());
        $blockers = $this->blockers($outstanding, $handover->checklist ?? [], $this->openSnagCount($handover));
        $history = $handover->workflow_history ?? [];
        $history[] = $this->workflowEvent(empty($blockers) ? 'ready' : 'blocked', $actor, $note);

        $handover->forceFill([
            'financial_outstanding' => $outstanding,
            'blockers' => $blockers,
            'status' => empty($blockers) ? 'ready' : 'blocked',
            'workflow_history' => $history,
        ])->save();
    }

    private function financialOutstanding(Booking $booking): float
    {
        $approvedCollections = (float) CollectionReceipt::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'approved')
            ->sum('amount');

        return round(max((float) $booking->net_receivable - $approvedCollections, 0), 2);
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

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<int, array<string, mixed>>
     */
    private function blockers(float $outstanding, array $checklist, int $openSnags): array
    {
        $blockers = [];

        if ($outstanding > 0) {
            $blockers[] = ['code' => 'financial_outstanding', 'message' => 'Financial outstanding must be zero before handover.', 'amount' => $outstanding];
        }

        $pendingChecklist = collect($checklist)
            ->filter(fn (array $item): bool => (bool) ($item['required'] ?? false) && ! (bool) ($item['completed'] ?? false))
            ->pluck('code')
            ->values()
            ->all();

        if (! empty($pendingChecklist)) {
            $blockers[] = ['code' => 'pending_checklist', 'message' => 'Required checklist items are pending.', 'items' => $pendingChecklist];
        }

        if ($openSnags > 0) {
            $blockers[] = ['code' => 'open_snags', 'message' => 'Open snags must be resolved before handover.', 'count' => $openSnags];
        }

        return $blockers;
    }

    private function openSnagCount(PossessionHandover $handover): int
    {
        return HandoverSnag::query()
            ->where('possession_handover_id', $handover->id)
            ->where('status', 'open')
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultChecklist(): array
    {
        return [
            ['code' => 'final_payment_clearance', 'label' => 'Final payment clearance', 'required' => true, 'completed' => false],
            ['code' => 'documents_verified', 'label' => 'Customer and booking documents verified', 'required' => true, 'completed' => false],
            ['code' => 'unit_inspection_done', 'label' => 'Unit inspection completed', 'required' => true, 'completed' => false],
            ['code' => 'keys_ready', 'label' => 'Keys and access cards ready', 'required' => true, 'completed' => false],
        ];
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

    private function nextHandoverNumber(): string
    {
        return sprintf('PH-%04d', PossessionHandover::query()->withTrashed()->count() + 1001);
    }

    private function nextSnagNumber(): string
    {
        return sprintf('SNAG-%04d', HandoverSnag::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, string>
     */
    private function handoverRelations(): array
    {
        return ['booking', 'project', 'unit', 'customer', 'initiatedBy', 'completedBy', 'snags.reportedBy', 'snags.resolvedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function snagRelations(): array
    {
        return ['handover', 'reportedBy', 'resolvedBy'];
    }
}
