<?php

namespace App\Services\Payroll;

use App\Models\Booking;
use App\Models\CommissionItem;
use App\Models\CommissionRule;
use App\Models\CommissionRun;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
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
    public function createRule(array $data, User $actor, ?Request $request = null): CommissionRule
    {
        $companyId = $this->ruleCompanyId($data, $actor);

        $rule = CommissionRule::create([
            'company_id' => $companyId,
            'project_id' => $data['project_id'] ?? null,
            'created_by_user_id' => $actor->id,
            'rule_code' => $data['rule_code'],
            'name' => $data['name'],
            'rule_type' => $data['rule_type'],
            'basis' => $data['basis'],
            'rate_percent' => $data['rate_percent'] ?? 0,
            'fixed_amount' => $data['fixed_amount'] ?? 0,
            'target_amount' => $data['target_amount'] ?? 0,
            'slab_rules' => $data['slab_rules'] ?? null,
            'eligibility_rules' => $data['eligibility_rules'] ?? null,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'status' => $data['status'] ?? 'active',
            'workflow_history' => [
                $this->historyEvent($data['status'] ?? 'active', $actor, 'Commission rule configured.'),
            ],
            'metadata' => ['source' => 'commission_service'],
        ]);

        $this->auditLogger->record(
            $actor,
            'payroll.commission_rule.created',
            'Created commission rule',
            $rule,
            ['rule_code' => $rule->rule_code, 'rule_type' => $rule->rule_type, 'basis' => $rule->basis],
            $request,
        );

        return $rule->load(['project', 'createdBy']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function generateRun(array $data, User $actor, ?Request $request = null): CommissionRun
    {
        return DB::transaction(function () use ($data, $actor, $request): CommissionRun {
            $periodStart = Carbon::create((int) $data['period_year'], (int) $data['period_month'], 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            $rule = CommissionRule::query()
                ->whereKey($data['commission_rule_id'])
                ->lockForUpdate()
                ->first();

            if (! $rule || ! $this->companyScope->allows($actor, $rule->company_id)) {
                throw ValidationException::withMessages(['commission_rule_id' => 'No active commission rule was found for this company and period.']);
            }

            if (
                $rule->status !== 'active'
                || $rule->effective_from->greaterThan($periodEnd)
                || ($rule->effective_to !== null && $rule->effective_to->lessThan($periodStart))
            ) {
                throw ValidationException::withMessages(['commission_rule_id' => 'No active commission rule was found for this company and period.']);
            }

            $companyId = (int) $rule->company_id;

            if (CommissionRun::query()
                ->where('company_id', $companyId)
                ->where('commission_rule_id', $rule->id)
                ->where('period_year', $periodStart->year)
                ->where('period_month', $periodStart->month)
                ->exists()) {
                throw ValidationException::withMessages(['period_month' => 'A commission run already exists for this rule and period.']);
            }

            $sourceRows = $this->sourceRows($rule, $periodStart, $periodEnd);

            if ($sourceRows->isEmpty()) {
                throw ValidationException::withMessages(['period_month' => 'No eligible approved CRM/sales source records were found for this commission period.']);
            }

            $calculatedRows = $this->calculateRows($rule, $sourceRows);
            $payableRows = $calculatedRows->filter(fn (array $row): bool => $row['commission_amount'] > 0)->values();

            if ($payableRows->isEmpty()) {
                throw ValidationException::withMessages(['commission_rule_id' => 'Eligible source records were found, but the rule produced no payable commission.']);
            }

            $run = CommissionRun::create([
                'company_id' => $companyId,
                'commission_rule_id' => $rule->id,
                'generated_by_user_id' => $actor->id,
                'run_number' => $this->nextRunNumber(),
                'period_year' => $periodStart->year,
                'period_month' => $periodStart->month,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => 'generated',
                'item_count' => $payableRows->count(),
                'source_total' => round($sourceRows->sum('source_amount'), 2),
                'eligible_total' => round($payableRows->sum('eligible_amount'), 2),
                'commission_total' => round($payableRows->sum('commission_amount'), 2),
                'calculation_summary' => [
                    'rule_code' => $rule->rule_code,
                    'rule_type' => $rule->rule_type,
                    'basis' => $rule->basis,
                    'source_count' => $sourceRows->count(),
                    'payable_count' => $payableRows->count(),
                    'note' => $data['note'] ?? null,
                    'calculated_at' => now()->toISOString(),
                ],
                'workflow_history' => [
                    $this->historyEvent('generated', $actor, $data['note'] ?? 'Commission run generated.'),
                ],
            ]);

            foreach ($payableRows as $row) {
                CommissionItem::create([
                    'company_id' => $companyId,
                    'commission_run_id' => $run->id,
                    'commission_rule_id' => $rule->id,
                    'employee_id' => $row['employee_id'],
                    'booking_id' => $row['booking_id'],
                    'lead_id' => $row['lead_id'],
                    'partner_id' => $row['partner_id'],
                    'period_year' => $periodStart->year,
                    'period_month' => $periodStart->month,
                    'source_amount' => $row['source_amount'],
                    'eligible_amount' => $row['eligible_amount'],
                    'commission_amount' => $row['commission_amount'],
                    'status' => 'generated',
                    'rule_snapshot' => $this->ruleSnapshot($rule),
                    'metadata' => [
                        'booking_code' => $row['booking_code'],
                        'calculation_method' => $row['calculation_method'],
                    ],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'payroll.commission_run.generated',
                'Generated commission run',
                $run,
                [
                    'run_number' => $run->run_number,
                    'rule_code' => $rule->rule_code,
                    'period' => sprintf('%04d-%02d', $run->period_year, $run->period_month),
                    'item_count' => $run->item_count,
                    'commission_total' => $run->commission_total,
                ],
                $request,
            );

            return $run->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveRun(CommissionRun $commissionRun, array $data, User $actor, ?Request $request = null): CommissionRun
    {
        return DB::transaction(function () use ($commissionRun, $data, $actor, $request): CommissionRun {
            $run = CommissionRun::query()->whereKey($commissionRun->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $run->company_id, 'commission_run');

            if ($run->status !== 'generated') {
                throw ValidationException::withMessages(['commission_run' => 'Only generated commission runs can be approved.']);
            }

            if ($run->generated_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['commission_run' => 'The commission generator cannot approve the same run.']);
            }

            $history = $run->workflow_history ?? [];
            $history[] = $this->historyEvent('approved', $actor, $data['decision_note'] ?? 'Commission run approved for payroll inclusion.');

            $run->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            CommissionItem::where('company_id', $run->company_id)->where('commission_run_id', $run->id)->update(['status' => 'approved']);

            $this->auditLogger->record(
                $actor,
                'payroll.commission_run.approved',
                'Approved commission run',
                $run,
                ['run_number' => $run->run_number, 'commission_total' => $run->commission_total],
                $request,
            );

            return $run->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function rejectRun(CommissionRun $commissionRun, array $data, User $actor, ?Request $request = null): CommissionRun
    {
        return DB::transaction(function () use ($commissionRun, $data, $actor, $request): CommissionRun {
            $run = CommissionRun::query()->whereKey($commissionRun->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $run->company_id, 'commission_run');

            if ($run->status !== 'generated') {
                throw ValidationException::withMessages(['commission_run' => 'Only generated commission runs can be rejected.']);
            }

            $history = $run->workflow_history ?? [];
            $history[] = $this->historyEvent('rejected', $actor, $data['decision_note']);

            $run->forceFill([
                'status' => 'rejected',
                'approved_by_user_id' => $actor->id,
                'workflow_history' => $history,
            ])->save();

            CommissionItem::where('company_id', $run->company_id)->where('commission_run_id', $run->id)->update(['status' => 'rejected']);

            $this->auditLogger->record(
                $actor,
                'payroll.commission_run.rejected',
                'Rejected commission run',
                $run,
                ['run_number' => $run->run_number, 'decision_note' => $data['decision_note']],
                $request,
            );

            return $run->load($this->relations());
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sourceRows(CommissionRule $rule, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $bookings = Booking::query()
            ->with(['lead', 'partner', 'bookedBy.employee', 'collectionReceipts'])
            ->where('company_id', $rule->company_id)
            ->where('status', 'confirmed')
            ->whereDate('booked_on', '>=', $periodStart->toDateString())
            ->whereDate('booked_on', '<=', $periodEnd->toDateString())
            ->when($rule->project_id, fn ($query, int $projectId) => $query->where('project_id', $projectId))
            ->get();

        return $bookings->map(function (Booking $booking) use ($rule, $periodStart, $periodEnd): ?array {
            $employee = $booking->bookedBy?->employee;
            if (! $employee instanceof Employee || $employee->company_id !== $booking->company_id) {
                return null;
            }

            $sourceAmount = $rule->basis === 'collection_received'
                ? (float) $booking->collectionReceipts
                    ->filter(fn ($receipt): bool => $receipt->status === 'approved'
                        && $receipt->receipt_date >= $periodStart
                        && $receipt->receipt_date <= $periodEnd)
                    ->sum('amount')
                : (float) $booking->agreement_value;

            if ($sourceAmount <= 0) {
                return null;
            }

            return [
                'employee_id' => $employee->id,
                'booking_id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'lead_id' => $booking->lead_id,
                'partner_id' => $booking->partner_id,
                'source_amount' => round($sourceAmount, 2),
            ];
        })->filter()->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $sourceRows
     * @return Collection<int, array<string, mixed>>
     */
    private function calculateRows(CommissionRule $rule, Collection $sourceRows): Collection
    {
        if ($rule->rule_type === 'target') {
            return $this->calculateTargetRows($rule, $sourceRows);
        }

        return $sourceRows->map(function (array $row) use ($rule): array {
            $commission = match ($rule->rule_type) {
                'fixed' => (float) $rule->fixed_amount,
                'slab' => $this->slabCommission($row['source_amount'], $rule->slab_rules ?? []),
                default => $row['source_amount'] * ((float) $rule->rate_percent / 100),
            };

            return $row + [
                'eligible_amount' => $row['source_amount'],
                'commission_amount' => round($commission, 2),
                'calculation_method' => $rule->rule_type,
            ];
        })->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $sourceRows
     * @return Collection<int, array<string, mixed>>
     */
    private function calculateTargetRows(CommissionRule $rule, Collection $sourceRows): Collection
    {
        $employeeTotals = $sourceRows
            ->groupBy('employee_id')
            ->map(fn (Collection $rows): float => round((float) $rows->sum('source_amount'), 2));

        return $sourceRows->map(function (array $row) use ($rule, $employeeTotals): array {
            $employeeTotal = (float) $employeeTotals[$row['employee_id']];
            $qualified = $employeeTotal >= (float) $rule->target_amount;
            $commission = $qualified ? $row['source_amount'] * ((float) $rule->rate_percent / 100) : 0;

            return $row + [
                'eligible_amount' => $qualified ? $row['source_amount'] : 0,
                'commission_amount' => round($commission, 2),
                'calculation_method' => $qualified ? 'target_qualified' : 'target_not_met',
            ];
        })->values();
    }

    /**
     * @param array<int, array<string, mixed>> $slabs
     */
    private function slabCommission(float $sourceAmount, array $slabs): float
    {
        foreach ($slabs as $slab) {
            $from = (float) ($slab['from'] ?? 0);
            $to = isset($slab['to']) ? (float) $slab['to'] : null;

            if ($sourceAmount >= $from && ($to === null || $sourceAmount <= $to)) {
                return $sourceAmount * ((float) ($slab['rate_percent'] ?? 0) / 100);
            }
        }

        return 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleSnapshot(CommissionRule $rule): array
    {
        return [
            'rule_code' => $rule->rule_code,
            'name' => $rule->name,
            'rule_type' => $rule->rule_type,
            'basis' => $rule->basis,
            'rate_percent' => (float) $rule->rate_percent,
            'fixed_amount' => (float) $rule->fixed_amount,
            'target_amount' => (float) $rule->target_amount,
            'slab_rules' => $rule->slab_rules,
            'eligibility_rules' => $rule->eligibility_rules,
            'effective_from' => $rule->effective_from?->toDateString(),
            'effective_to' => $rule->effective_to?->toDateString(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ruleCompanyId(array $data, User $actor): int
    {
        if (! empty($data['project_id'])) {
            $project = Project::query()->whereKey($data['project_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $project->company_id, 'project_id');

            return (int) $project->company_id;
        }

        $companyId = $this->companyScope->companyIdFor($actor);

        if ($companyId === null || $companyId === 0) {
            throw ValidationException::withMessages(['rule_code' => 'A company assignment is required before creating commission rules.']);
        }

        return $companyId;
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
     * @return array<string, mixed>
     */
    private function historyEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextRunNumber(): string
    {
        return sprintf('COM-%05d', CommissionRun::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['rule.project', 'generatedBy', 'approvedBy', 'items.employee', 'items.booking', 'items.lead', 'items.partner'];
    }
}
