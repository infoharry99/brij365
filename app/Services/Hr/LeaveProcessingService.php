<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveProcessingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly SystemSettingResolver $settings,
        private readonly CompanyScopeService $companyScope,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createProcessingRun(array $data, User $actor, ?Request $request = null): LeaveProcessingRun
    {
        return DB::transaction(function () use ($data, $actor, $request): LeaveProcessingRun {
            $companyId = $this->resolveProcessingCompanyId($data, $actor);
            $periodYear = (int) $data['period_year'];
            $processingType = $data['processing_type'];

            if (LeaveProcessingRun::query()
                ->where('company_id', $companyId)
                ->where('period_year', $periodYear)
                ->where('processing_type', $processingType)
                ->where('status', 'posted')
                ->exists()) {
                throw ValidationException::withMessages(['period_year' => 'This leave processing run has already been posted for the selected year and type.']);
            }

            $balances = EmployeeLeaveBalance::query()
                ->with(['employee', 'leaveType'])
                ->where('company_id', $companyId)
                ->where('period_year', $periodYear)
                ->orderBy('employee_id')
                ->orderBy('leave_type_id')
                ->get();

            if ($balances->isEmpty()) {
                throw ValidationException::withMessages(['period_year' => 'No leave balances were found for the selected year.']);
            }

            $lineItems = $balances
                ->map(fn (EmployeeLeaveBalance $balance): array => $this->processingLine($balance, $processingType))
                ->values()
                ->all();

            $summary = [
                'employee_count' => collect($lineItems)->pluck('employee_id')->unique()->count(),
                'line_count' => count($lineItems),
                'total_accrual_days' => round(collect($lineItems)->sum('accrual_days'), 2),
                'total_carry_forward_days' => round(collect($lineItems)->sum('carry_forward_days'), 2),
                'total_lapse_days' => round(collect($lineItems)->sum('lapse_days'), 2),
            ];

            $run = LeaveProcessingRun::query()
                ->where('company_id', $companyId)
                ->where('period_year', $periodYear)
                ->where('processing_type', $processingType)
                ->where('status', 'preview')
                ->first();

            $payload = [
                'company_id' => $companyId,
                'created_by_user_id' => $actor->id,
                'period_year' => $periodYear,
                'processing_type' => $processingType,
                'status' => 'preview',
                'is_dry_run' => (bool) ($data['is_dry_run'] ?? true),
                'rules_snapshot' => $this->leaveRulesSnapshot($companyId),
                'summary' => $summary,
                'line_items' => $lineItems,
                'workflow_history' => [
                    $this->workflowEvent('preview', $actor, $data['note'] ?? 'Leave processing preview generated.'),
                ],
            ];

            if ($run) {
                $run->forceFill($payload)->save();
            } else {
                $run = LeaveProcessingRun::create($payload + [
                    'run_number' => $this->nextProcessingRunNumber(),
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'hr.leave_processing.previewed',
                'Previewed leave processing run',
                $run,
                ['run_number' => $run->run_number, 'period_year' => $periodYear, 'processing_type' => $processingType],
                $request,
            );

            return $run->load($this->processingRelations());
        });
    }

    public function postProcessingRun(LeaveProcessingRun $leaveProcessingRun, User $actor, ?Request $request = null, ?string $note = null): LeaveProcessingRun
    {
        return DB::transaction(function () use ($leaveProcessingRun, $actor, $request, $note): LeaveProcessingRun {
            $run = LeaveProcessingRun::query()->whereKey($leaveProcessingRun->id)->lockForUpdate()->firstOrFail();

            if ($run->status !== 'preview') {
                throw ValidationException::withMessages(['leave_processing_run' => 'Only preview leave processing runs can be posted.']);
            }

            if (LeaveProcessingRun::query()
                ->where('company_id', $run->company_id)
                ->where('period_year', $run->period_year)
                ->where('processing_type', $run->processing_type)
                ->where('status', 'posted')
                ->whereKeyNot($run->id)
                ->exists()) {
                throw ValidationException::withMessages(['leave_processing_run' => 'This leave processing run has already been posted.']);
            }

            foreach ($run->line_items ?? [] as $line) {
                $balance = EmployeeLeaveBalance::query()
                    ->whereKey($line['balance_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($run->processing_type === 'monthly_accrual') {
                    $this->postMonthlyAccrual($balance, (float) $line['accrual_days'], $run, $actor);
                }

                if ($run->processing_type === 'year_end') {
                    $this->postYearEnd($balance, (float) $line['carry_forward_days'], (float) $line['lapse_days'], $run, $actor);
                }
            }

            $history = $run->workflow_history ?? [];
            $history[] = $this->workflowEvent('posted', $actor, $note ?? 'Leave processing run posted.');

            $run->forceFill([
                'status' => 'posted',
                'is_dry_run' => false,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.leave_processing.posted',
                'Posted leave processing run',
                $run,
                ['run_number' => $run->run_number, 'period_year' => $run->period_year, 'processing_type' => $run->processing_type],
                $request,
            );

            return $run->load($this->processingRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitEncashment(array $data, User $actor, ?Request $request = null): LeaveEncashment
    {
        return DB::transaction(function () use ($data, $actor, $request): LeaveEncashment {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $balance = EmployeeLeaveBalance::query()
                ->with('leaveType')
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $data['leave_type_id'])
                ->where('period_year', $data['period_year'])
                ->lockForUpdate()
                ->firstOrFail();

            $days = round((float) $data['requested_days'], 2);

            if (! $balance->leaveType->encashment_enabled) {
                throw ValidationException::withMessages(['leave_type_id' => 'This leave type is not eligible for encashment.']);
            }

            if ((float) $balance->available_days < $days) {
                throw ValidationException::withMessages(['requested_days' => 'Requested encashment days exceed available balance.']);
            }

            $dailyRate = $this->dailyRate($employee);
            $gross = round($dailyRate * $days, 2);
            $tax = round($gross * $this->encashmentTaxRate($employee->company_id), 2);

            $encashment = LeaveEncashment::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $balance->leave_type_id,
                'requested_by_user_id' => $actor->id,
                'encashment_number' => $this->nextEncashmentNumber(),
                'period_year' => (int) $data['period_year'],
                'status' => 'submitted',
                'requested_days' => $days,
                'daily_rate' => $dailyRate,
                'gross_amount' => $gross,
                'tax_amount' => $tax,
                'net_amount' => round($gross - $tax, 2),
                'calculation_snapshot' => [
                    'available_days_at_request' => (float) $balance->available_days,
                    'monthly_ctc' => (float) $employee->monthly_ctc,
                    'formula' => 'requested_days * monthly_ctc / 30 - configured_tax',
                    'tax_rate' => $this->encashmentTaxRate($employee->company_id),
                ],
                'request_note' => $data['request_note'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Leave encashment submitted.'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.leave_encashment.submitted',
                'Submitted leave encashment request',
                $encashment,
                ['encashment_number' => $encashment->encashment_number, 'requested_days' => $days],
                $request,
            );

            return $encashment->load($this->encashmentRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveEncashment(LeaveEncashment $leaveEncashment, array $data, User $actor, ?Request $request = null): LeaveEncashment
    {
        return DB::transaction(function () use ($leaveEncashment, $data, $actor, $request): LeaveEncashment {
            $encashment = LeaveEncashment::query()->whereKey($leaveEncashment->id)->lockForUpdate()->firstOrFail();
            $balance = EmployeeLeaveBalance::query()
                ->where('employee_id', $encashment->employee_id)
                ->where('leave_type_id', $encashment->leave_type_id)
                ->where('period_year', $encashment->period_year)
                ->lockForUpdate()
                ->firstOrFail();

            $approvedDays = round((float) ($data['approved_days'] ?? $encashment->requested_days), 2);

            if ($approvedDays > (float) $encashment->requested_days) {
                throw ValidationException::withMessages(['approved_days' => 'Approved days cannot exceed requested days.']);
            }

            if ((float) $balance->available_days < $approvedDays) {
                throw ValidationException::withMessages(['approved_days' => 'Approved encashment days exceed available balance.']);
            }

            $dailyRate = $this->dailyRate($encashment->employee);
            $gross = round($dailyRate * $approvedDays, 2);
            $tax = round($gross * $this->encashmentTaxRate($encashment->company_id), 2);
            $history = $encashment->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['decision_note'] ?? 'Leave encashment approved.');

            $encashment->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_days' => $approvedDays,
                'daily_rate' => $dailyRate,
                'gross_amount' => $gross,
                'tax_amount' => $tax,
                'net_amount' => round($gross - $tax, 2),
                'decision_note' => $data['decision_note'] ?? null,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $balance->forceFill([
                'available_days' => round((float) $balance->available_days - $approvedDays, 2),
                'adjusted_days' => round((float) $balance->adjusted_days - $approvedDays, 2),
                'ledger' => $this->appendBalanceLedger($balance, 'leave_encashment_approved', -$approvedDays, $encashment, $actor),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.leave_encashment.approved',
                'Approved leave encashment request',
                $encashment,
                ['encashment_number' => $encashment->encashment_number, 'approved_days' => $approvedDays, 'net_amount' => $encashment->net_amount],
                $request,
            );

            return $encashment->load($this->encashmentRelations());
        });
    }

    public function rejectEncashment(LeaveEncashment $leaveEncashment, array $data, User $actor, ?Request $request = null): LeaveEncashment
    {
        return DB::transaction(function () use ($leaveEncashment, $data, $actor, $request): LeaveEncashment {
            $encashment = LeaveEncashment::query()->whereKey($leaveEncashment->id)->lockForUpdate()->firstOrFail();
            $history = $encashment->workflow_history ?? [];
            $history[] = $this->workflowEvent('rejected', $actor, $data['decision_note']);

            $encashment->forceFill([
                'status' => 'rejected',
                'approved_by_user_id' => $actor->id,
                'decision_note' => $data['decision_note'],
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record($actor, 'hr.leave_encashment.rejected', 'Rejected leave encashment request', $encashment, ['encashment_number' => $encashment->encashment_number], $request);

            return $encashment->load($this->encashmentRelations());
        });
    }

    public function markEncashmentPayroll(LeaveEncashment $leaveEncashment, array $data, User $actor, ?Request $request = null): LeaveEncashment
    {
        return DB::transaction(function () use ($leaveEncashment, $data, $actor, $request): LeaveEncashment {
            $encashment = LeaveEncashment::query()->whereKey($leaveEncashment->id)->lockForUpdate()->firstOrFail();
            $history = $encashment->workflow_history ?? [];
            $history[] = $this->workflowEvent('payroll_marked', $actor, $data['note'] ?? 'Leave encashment marked for payroll inclusion.');

            $encashment->forceFill([
                'status' => 'payroll_marked',
                'payroll_marked_by_user_id' => $actor->id,
                'payroll_reference' => $data['payroll_reference'],
                'payroll_marked_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->notifications->sendToPermission(['payroll.manage'], [
                'category' => 'payroll',
                'severity' => 'info',
                'title' => 'Leave encashment ready for payroll',
                'body' => "{$encashment->employee->name}'s leave encashment is approved for payroll inclusion.",
                'action_url' => route('hr.leave-encashments.index', ['status' => 'payroll_marked'], false),
                'payload' => ['encashment_number' => $encashment->encashment_number, 'net_amount' => (float) $encashment->net_amount],
            ], $actor, $encashment, $encashment->company_id);

            $this->auditLogger->record($actor, 'hr.leave_encashment.payroll_marked', 'Marked leave encashment for payroll inclusion', $encashment, ['encashment_number' => $encashment->encashment_number, 'payroll_reference' => $encashment->payroll_reference], $request);

            return $encashment->load($this->encashmentRelations());
        });
    }

    /**
     * @return array<int, string>
     */
    public function processingRelations(): array
    {
        return ['createdBy', 'postedBy'];
    }

    /**
     * @return array<int, string>
     */
    public function encashmentRelations(): array
    {
        return ['employee.user', 'leaveType', 'requestedBy', 'approvedBy', 'payrollMarkedBy'];
    }

    private function processingLine(EmployeeLeaveBalance $balance, string $processingType): array
    {
        $leaveType = $balance->leaveType;
        $available = (float) $balance->available_days;
        $accrual = $processingType === 'monthly_accrual' ? round((float) $leaveType->annual_entitlement_days / 12, 2) : 0.0;
        $carry = $processingType === 'year_end' && $leaveType->carry_forward_enabled
            ? min($available, (float) $leaveType->max_carry_forward_days)
            : 0.0;
        $lapse = $processingType === 'year_end' ? max($available - $carry, 0) : 0.0;

        return [
            'balance_id' => $balance->id,
            'employee_id' => $balance->employee_id,
            'employee_code' => $balance->employee?->employee_code,
            'employee_name' => $balance->employee?->name,
            'leave_type_id' => $balance->leave_type_id,
            'leave_type_code' => $leaveType->code,
            'opening_balance_days' => (float) $balance->opening_balance_days,
            'available_before' => $available,
            'accrual_days' => $accrual,
            'carry_forward_days' => round($carry, 2),
            'lapse_days' => round($lapse, 2),
        ];
    }

    private function postMonthlyAccrual(EmployeeLeaveBalance $balance, float $days, LeaveProcessingRun $run, User $actor): void
    {
        if ($days <= 0) {
            return;
        }

        $balance->forceFill([
            'accrued_days' => round((float) $balance->accrued_days + $days, 2),
            'available_days' => round((float) $balance->available_days + $days, 2),
            'ledger' => $this->appendRunLedger($balance, 'monthly_accrual_posted', $days, $run, $actor),
        ])->save();
    }

    private function postYearEnd(EmployeeLeaveBalance $balance, float $carry, float $lapse, LeaveProcessingRun $run, User $actor): void
    {
        $balance->forceFill([
            'available_days' => round($carry, 2),
            'adjusted_days' => round((float) $balance->adjusted_days - $lapse, 2),
            'ledger' => $this->appendRunLedger($balance, 'year_end_posted', -$lapse, $run, $actor, ['carry_forward_days' => $carry]),
        ])->save();

        EmployeeLeaveBalance::updateOrCreate(
            [
                'employee_id' => $balance->employee_id,
                'leave_type_id' => $balance->leave_type_id,
                'period_year' => $run->period_year + 1,
            ],
            [
                'company_id' => $balance->company_id,
                'opening_balance_days' => round($carry, 2),
                'accrued_days' => 0,
                'used_days' => 0,
                'pending_days' => 0,
                'adjusted_days' => 0,
                'available_days' => round($carry, 2),
                'ledger' => [
                    [
                        'event' => 'carry_forward_opening',
                        'days' => round($carry, 2),
                        'source_run_number' => $run->run_number,
                        'actor_user_id' => $actor->id,
                        'at' => now()->toISOString(),
                    ],
                ],
            ],
        );
    }

    private function dailyRate(Employee $employee): float
    {
        return round((float) $employee->monthly_ctc / 30, 2);
    }

    private function encashmentTaxRate(int $companyId): float
    {
        $rate = data_get($this->settings->value($companyId, 'hr.leave.rules'), 'encashment_tax_rate', 0.10);

        return is_numeric($rate) ? (float) $rate : 0.10;
    }

    private function leaveRulesSnapshot(int $companyId): array
    {
        $leaveRules = $this->settings->value($companyId, 'hr.leave.rules', [
            'encashment_tax_rate' => 0.10,
        ]);

        return [
            'leave_types' => \App\Models\LeaveType::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->get(['code', 'annual_entitlement_days', 'carry_forward_enabled', 'max_carry_forward_days', 'encashment_enabled'])
                ->map(fn ($type): array => [
                    'code' => $type->code,
                    'annual_entitlement_days' => (float) $type->annual_entitlement_days,
                    'carry_forward_enabled' => $type->carry_forward_enabled,
                    'max_carry_forward_days' => (float) $type->max_carry_forward_days,
                    'encashment_enabled' => $type->encashment_enabled,
                ])
                ->values()
                ->all(),
            'rules_setting' => [
                'setting_key' => 'hr.leave.rules',
                'encashment_tax_rate' => is_numeric($leaveRules['encashment_tax_rate'] ?? null)
                    ? (float) $leaveRules['encashment_tax_rate']
                    : 0.10,
                'monthly_accrual_enabled' => (bool) ($leaveRules['monthly_accrual_enabled'] ?? true),
                'year_end_processing_enabled' => (bool) ($leaveRules['year_end_processing_enabled'] ?? true),
                'encashment_formula' => $leaveRules['encashment_formula'] ?? 'approved_days * monthly_ctc / 30 - configured_tax',
            ],
            'encashment_tax_rate' => is_numeric($leaveRules['encashment_tax_rate'] ?? null)
                ? (float) $leaveRules['encashment_tax_rate']
                : 0.10,
        ];
    }

    private function appendRunLedger(EmployeeLeaveBalance $balance, string $event, float $days, LeaveProcessingRun $run, User $actor, array $extra = []): array
    {
        $ledger = $balance->ledger ?? [];
        $ledger[] = $extra + [
            'event' => $event,
            'days' => round($days, 2),
            'run_number' => $run->run_number,
            'actor_user_id' => $actor->id,
            'at' => now()->toISOString(),
        ];

        return $ledger;
    }

    private function appendBalanceLedger(EmployeeLeaveBalance $balance, string $event, float $days, LeaveEncashment $encashment, User $actor): array
    {
        $ledger = $balance->ledger ?? [];
        $ledger[] = [
            'event' => $event,
            'days' => round($days, 2),
            'encashment_number' => $encashment->encashment_number,
            'actor_user_id' => $actor->id,
            'at' => now()->toISOString(),
        ];

        return $ledger;
    }

    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveProcessingCompanyId(array $data, User $actor): int
    {
        if (isset($data['company_id']) && $this->companyScope->allows($actor, $data['company_id'])) {
            return (int) $data['company_id'];
        }

        $companyId = $this->companyScope->companyIdFor($actor);

        if ($companyId === null || $companyId === 0) {
            throw ValidationException::withMessages(['company_id' => 'A company is required to create a leave processing run.']);
        }

        return $companyId;
    }

    private function nextProcessingRunNumber(): string
    {
        return sprintf('LPR-%05d', LeaveProcessingRun::query()->withTrashed()->count() + 10001);
    }

    private function nextEncashmentNumber(): string
    {
        return sprintf('LEN-%05d', LeaveEncashment::query()->withTrashed()->count() + 10001);
    }
}
