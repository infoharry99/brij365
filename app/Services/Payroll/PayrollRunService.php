<?php

namespace App\Services\Payroll;

use App\Domain\Payroll\Data\GovernedStatutoryRuleSet;
use App\Domain\Payroll\Services\AnnualTaxProjectionContextFactory;
use App\Domain\Payroll\Services\PayrollCalculationSnapshotWriter;
use App\Domain\Payroll\Services\PayrollCalculationSnapshotVerifier;
use App\Domain\Payroll\Services\StatutoryPayrollEngine;
use App\Domain\Payroll\Services\StatutoryPayrollCutoverManifestResolver;
use App\Domain\Payroll\Services\StatutoryRulePackResolver;
use App\Domain\Payroll\ValueObjects\MinorMoney;
use App\Models\AttendancePeriodLock;
use App\Models\CommissionItem;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryAssignment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollRunService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly StatutoryPayrollCutoverManifestResolver $cutoverManifestResolver,
        private readonly StatutoryRulePackResolver $statutoryRulePackResolver,
        private readonly StatutoryPayrollEngine $statutoryPayrollEngine,
        private readonly AnnualTaxProjectionContextFactory $taxProjectionContextFactory,
        private readonly PayrollCalculationSnapshotWriter $snapshotWriter,
        private readonly PayrollCalculationSnapshotVerifier $snapshotVerifier,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function generate(array $data, User $actor, ?Request $request = null): PayrollRun
    {
        return DB::transaction(function () use ($data, $actor, $request): PayrollRun {
            $companyId = $this->companyScope->companyIdFor($actor);

            if ($companyId === null || $companyId === 0) {
                throw ValidationException::withMessages([
                    'period_month' => 'Payroll generation requires a valid company scope.',
                ]);
            }

            $periodStart = Carbon::create((int) $data['period_year'], (int) $data['period_month'], 1)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();

            if (PayrollRun::query()->where('company_id', $companyId)->where('period_year', $periodStart->year)->where('period_month', $periodStart->month)->exists()) {
                throw ValidationException::withMessages(['period_month' => 'A payroll run already exists for this company and period.']);
            }

            $assignments = SalaryAssignment::query()
                ->with(['employee', 'salaryStructure.components.payrollComponent'])
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->whereDate('effective_from', '<=', $periodEnd->toDateString())
                ->where(function ($query) use ($periodStart): void {
                    $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart->toDateString());
                })
                ->get()
                ->unique('employee_id')
                ->values();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages(['period_month' => 'No active salary assignments were found for this payroll period.']);
            }

            $cutoverManifest = $this->cutoverManifestResolver->resolve($companyId, $periodEnd);

            /** @var array<string, GovernedStatutoryRuleSet> $rulesByState */
            $rulesByState = [];
            foreach ($assignments as $assignment) {
                $state = strtoupper(trim((string) $assignment->employee?->statutory_state));
                $cacheKey = $state === '' ? '__missing__' : $state;
                if (isset($rulesByState[$cacheKey])) {
                    continue;
                }

                if ($cutoverManifest->requiresCompleteCutover() && $state === '') {
                    throw ValidationException::withMessages([
                        'employees' => 'Every employee in governed-required payroll requires an authoritative statutory state.',
                    ]);
                }

                $rules = $cutoverManifest->usesGovernedPacks()
                    ? $this->statutoryRulePackResolver->resolve($companyId, $state ?: null, $periodEnd)
                    : new GovernedStatutoryRuleSet;
                $requiredKeys = $cutoverManifest->requiredPackKeysForState($state ?: null);

                if ($cutoverManifest->requiresCompleteCutover() && $requiredKeys === []) {
                    throw ValidationException::withMessages([
                        'statutory_rules' => 'The active governed-required payroll cutover manifest declares no required statutory packs for state '.$state.'.',
                    ]);
                }

                $missingKeys = array_values(array_diff($requiredKeys, $rules->settingKeys()));
                if ($missingKeys !== []) {
                    throw ValidationException::withMessages([
                        'statutory_rules' => 'Required verified statutory packs are missing or inapplicable for state '.$state.': '.implode(', ', $missingKeys).'.',
                    ]);
                }

                $replacementCodes = $cutoverManifest->replacementComponentCodesForState(
                    $rules->settingKeys(),
                    $state ?: null,
                );
                $rulesByState[$cacheKey] = $rules->withCutoverManifest($cutoverManifest, $replacementCodes);
            }

            $governed = collect($rulesByState)->contains(fn (GovernedStatutoryRuleSet $rules): bool => $rules->isGoverned());
            $attendanceLock = $governed
                ? AttendancePeriodLock::query()
                    ->where('company_id', $companyId)
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString())
                    ->where('status', 'finalized')
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first()
                : null;

            if ($governed && $attendanceLock === null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Governed payroll requires a finalized attendance period for the exact payroll dates.',
                ]);
            }

            $attendanceSnapshots = $attendanceLock === null
                ? collect()
                : PayrollAttendanceSnapshot::query()
                    ->where('attendance_period_lock_id', $attendanceLock->id)
                    ->whereIn('employee_id', $assignments->pluck('employee_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('employee_id');

            if ($governed) {
                $missingEmployeeIds = $assignments
                    ->filter(function (SalaryAssignment $assignment) use ($rulesByState, $attendanceSnapshots): bool {
                        $state = strtoupper(trim((string) $assignment->employee?->statutory_state));
                        $rules = $rulesByState[$state === '' ? '__missing__' : $state];

                        return $rules->isGoverned() && ! $attendanceSnapshots->has($assignment->employee_id);
                    })
                    ->pluck('employee_id')
                    ->values();

                if ($missingEmployeeIds->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Finalized payroll attendance snapshots are missing for employee IDs '.$missingEmployeeIds->join(', ').'.',
                    ]);
                }
            }

            $run = PayrollRun::create([
                'company_id' => $companyId,
                'generated_by_user_id' => $actor->id,
                'run_number' => $this->nextRunNumber(),
                'period_year' => $periodStart->year,
                'period_month' => $periodStart->month,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'working_days' => $data['working_days'],
                'status' => 'generated',
                'metadata' => [
                    'source' => 'payroll_run_service',
                    'calculation_mode' => $governed ? 'governed_verified' : 'legacy_non_authoritative',
                    'statutory_cutover_mode' => $cutoverManifest->mode,
                    'statutory_cutover_manifest' => [
                        'setting_id' => $cutoverManifest->settingId,
                        'version' => $cutoverManifest->settingVersion,
                        'checksum' => $cutoverManifest->checksum,
                    ],
                    'attendance_period_lock_id' => $attendanceLock?->id,
                    'statutory_setting_ids' => collect($rulesByState)->flatMap(fn (GovernedStatutoryRuleSet $rules): array => $rules->settingIds())->unique()->values()->all(),
                ],
            ]);

            $grossTotal = MinorMoney::zero();
            $deductionTotal = MinorMoney::zero();
            $netTotal = MinorMoney::zero();
            $commissionItems = $this->approvedCommissionItems($companyId, $periodStart->year, $periodStart->month);

            foreach ($assignments as $assignment) {
                $state = strtoupper(trim((string) $assignment->employee?->statutory_state));
                $rules = $rulesByState[$state === '' ? '__missing__' : $state];
                $employeeCommissions = $commissionItems->get($assignment->employee_id, collect());
                $attendanceSnapshot = $rules->isGoverned() ? $attendanceSnapshots->get($assignment->employee_id) : null;
                $taxProjectionDefinition = $this->annualTaxProjectionDefinition($rules);
                $taxProjectionContext = $taxProjectionDefinition === null
                    ? null
                    : $this->taxProjectionContextFactory->build(
                        $companyId,
                        $assignment->employee_id,
                        $periodStart,
                        $taxProjectionDefinition,
                    );
                $result = $this->statutoryPayrollEngine->calculate(
                    $assignment,
                    $employeeCommissions,
                    (int) $data['working_days'],
                    $rules,
                    $attendanceSnapshot,
                    $taxProjectionContext,
                );
                $item = $this->createRunItem($run, $assignment, $result);
                $this->snapshotWriter->write($item, $assignment, $actor, $result);
                $grossTotal = $grossTotal->add($result->gross);
                $deductionTotal = $deductionTotal->add($result->deductions);
                $netTotal = $netTotal->add($result->net);

                if ($employeeCommissions->isNotEmpty()) {
                    CommissionItem::whereIn('id', $employeeCommissions->pluck('id')->all())->update([
                        'status' => 'payroll_included',
                        'payroll_run_item_id' => $item->id,
                        'payroll_included_at' => now(),
                    ]);
                }
            }

            $run->forceFill([
                'gross_earnings' => $grossTotal->toDecimal(),
                'total_deductions' => $deductionTotal->toDecimal(),
                'net_payable' => $netTotal->toDecimal(),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'payroll.run.generated',
                'Generated payroll run',
                $run,
                [
                    'run_number' => $run->run_number,
                    'period' => sprintf('%04d-%02d', $run->period_year, $run->period_month),
                    'employee_count' => $assignments->count(),
                    'net_payable' => $run->net_payable,
                ],
                $request,
            );

            return $run->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(PayrollRun $payrollRun, User $actor, array $data = [], ?Request $request = null): PayrollRun
    {
        return DB::transaction(function () use ($payrollRun, $actor, $data, $request): PayrollRun {
            $run = PayrollRun::query()->whereKey($payrollRun->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== 'generated') {
                throw ValidationException::withMessages(['payroll_run' => 'Only generated payroll runs can be approved.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $run->company_id)) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'The selected payroll run is outside your company scope.',
                ]);
            }

            if ($run->generated_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['payroll_run' => 'The payroll generator cannot approve the same payroll run.']);
            }

            if ((bool) data_get($run->metadata, 'attendance_snapshot_stale', false)) {
                throw ValidationException::withMessages([
                    'payroll_run' => 'This payroll run is marked stale because its attendance snapshot was reopened or changed. Regenerate the run before approval.',
                ]);
            }

            if (data_get($run->metadata, 'calculation_mode') === 'governed_verified') {
                $this->assertGovernedRunReadyForApproval($run);
            }

            // Finalize child rows while the generated parent is still mutable. The
            // approved parent transition below makes both the run and its rows immutable.
            PayrollRunItem::where('payroll_run_id', $run->id)->update(['status' => 'approved']);

            $run->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'metadata' => array_merge($run->metadata ?? [], [
                    'approval_note' => $data['note'] ?? null,
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'payroll.run.approved',
                'Approved payroll run',
                $run,
                ['run_number' => $run->run_number, 'net_payable' => $run->net_payable, 'note' => $data['note'] ?? null],
                $request,
            );

            return $run->load($this->relations());
        });
    }

    private function createRunItem(
        PayrollRun $run,
        SalaryAssignment $assignment,
        \App\Domain\Payroll\Data\PayrollCalculationResult $result,
    ): PayrollRunItem
    {
        $structure = $assignment->salaryStructure;

        return PayrollRunItem::create([
            'payroll_run_id' => $run->id,
            'company_id' => $run->company_id,
            'employee_id' => $assignment->employee_id,
            'salary_structure_id' => $structure->id,
            'monthly_ctc' => $structure->monthly_ctc,
            'payable_days' => sprintf('%d.%02d', intdiv($result->payableDaysHundredths, 100), $result->payableDaysHundredths % 100),
            'gross_earnings' => $result->gross->toDecimal(),
            'total_deductions' => $result->deductions->toDecimal(),
            'net_payable' => $result->net->toDecimal(),
            'component_breakup' => $result->componentBreakup,
            'status' => 'generated',
        ]);
    }

    private function nextRunNumber(): string
    {
        return sprintf('PAY-%04d', PayrollRun::query()->withTrashed()->count() + 1001);
    }

    /** @return array<string, mixed>|null */
    private function annualTaxProjectionDefinition(GovernedStatutoryRuleSet $rules): ?array
    {
        $definitions = collect($rules->rules)
            ->flatMap(fn (array $rule) => (array) data_get($rule, 'jurisdiction.lines', []))
            ->filter(fn (mixed $definition): bool => is_array($definition) && ($definition['method'] ?? null) === 'annual_tax_projection')
            ->values();

        if ($definitions->count() > 1) {
            throw ValidationException::withMessages([
                'statutory_rules' => 'Only one annual tax projection line may apply to an employee in a payroll period.',
            ]);
        }

        $definition = $definitions->first();

        return is_array($definition) ? $definition : null;
    }

    private function assertGovernedRunReadyForApproval(PayrollRun $run): void
    {
        $attendanceLockId = (int) data_get($run->metadata, 'attendance_period_lock_id', 0);
        $lockIsFinalized = $attendanceLockId > 0 && AttendancePeriodLock::query()
            ->whereKey($attendanceLockId)
            ->where('company_id', $run->company_id)
            ->where('status', 'finalized')
            ->exists();

        if (! $lockIsFinalized) {
            throw ValidationException::withMessages([
                'payroll_run' => 'The finalized attendance period used for this governed payroll run is no longer valid. Regenerate the run before approval.',
            ]);
        }

        $run->loadMissing('items.calculationSnapshot.lines');
        $invalidItems = $run->items->filter(function (PayrollRunItem $item) use ($attendanceLockId): bool {
            $snapshot = $item->calculationSnapshot;

            return $snapshot === null
                || $snapshot->payroll_attendance_snapshot_id === null
                || data_get($snapshot->rule_context, 'mode') !== 'governed_verified'
                || strlen((string) $snapshot->input_hash) !== 64
                || strlen((string) $snapshot->result_hash) !== 64
                || ! PayrollAttendanceSnapshot::query()
                    ->whereKey($snapshot->payroll_attendance_snapshot_id)
                    ->where('attendance_period_lock_id', $attendanceLockId)
                    ->exists();
        });

        if ($invalidItems->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payroll_run' => 'One or more governed payroll calculation snapshots are missing or no longer match the finalized attendance period.',
            ]);
        }

        foreach ($run->items as $item) {
            $this->snapshotVerifier->assertGovernedIntegrity($item->calculationSnapshot, 'payroll_run');
        }

        $gross = MinorMoney::zero();
        $deductions = MinorMoney::zero();
        $net = MinorMoney::zero();
        foreach ($run->items as $item) {
            $gross = $gross->add(MinorMoney::fromDecimal((string) $item->gross_earnings));
            $deductions = $deductions->add(MinorMoney::fromDecimal((string) $item->total_deductions));
            $net = $net->add(MinorMoney::fromDecimal((string) $item->net_payable));
        }
        if ($gross->minor !== MinorMoney::fromDecimal((string) $run->gross_earnings)->minor
            || $deductions->minor !== MinorMoney::fromDecimal((string) $run->total_deductions)->minor
            || $net->minor !== MinorMoney::fromDecimal((string) $run->net_payable)->minor) {
            throw ValidationException::withMessages([
                'payroll_run' => 'The governed payroll run totals no longer reconcile to its immutable employee calculation snapshots.',
            ]);
        }
    }

    /**
     * @return Collection<int, Collection<int, CommissionItem>>
     */
    private function approvedCommissionItems(int $companyId, int $periodYear, int $periodMonth): \Illuminate\Support\Collection
    {
        return CommissionItem::query()
            ->where('company_id', $companyId)
            ->where('period_year', $periodYear)
            ->where('period_month', $periodMonth)
            ->where('status', 'approved')
            ->whereNull('payroll_run_item_id')
            ->lockForUpdate()
            ->get()
            ->groupBy('employee_id');
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['generatedBy', 'approvedBy', 'items.employee', 'items.calculationSnapshot.lines'];
    }
}
