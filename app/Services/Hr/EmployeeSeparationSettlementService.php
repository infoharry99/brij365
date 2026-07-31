<?php

namespace App\Services\Hr;

use App\Models\AttendanceRegularizationRequest;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSeparationSettlement;
use App\Models\ExpenseClaim;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSeparationSettlementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function initiate(array $data, User $actor, ?Request $request = null): EmployeeSeparationSettlement
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeSeparationSettlement {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $calculation = $this->calculate($employee, $data);

            $settlement = EmployeeSeparationSettlement::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'initiated_by_user_id' => $actor->id,
                'settlement_number' => $this->nextSettlementNumber(),
                'separation_type' => $data['separation_type'],
                'status' => 'initiated',
                'resignation_date' => $data['resignation_date'] ?? null,
                'last_working_date' => $data['last_working_date'],
                'settlement_date' => $data['settlement_date'] ?? null,
                'reason' => $data['reason'] ?? null,
                ...$this->amountPayload($calculation),
                'calculation_breakdown' => $calculation['breakdown'],
                'clearance_blockers' => $calculation['blockers'],
                'workflow_history' => [
                    $this->workflowEvent('initiated', $actor, 'Separation settlement initiated.'),
                ],
            ]);

            $employee->forceFill(['status' => 'on_notice'])->save();

            $this->auditLogger->record(
                $actor,
                'hr.separation_settlement.initiated',
                'Initiated employee separation settlement',
                $settlement,
                ['settlement_number' => $settlement->settlement_number, 'employee_code' => $employee->employee_code, 'net_payable' => $settlement->net_payable],
                $request,
            );

            return $settlement->load($this->relations());
        });
    }

    public function hrApprove(EmployeeSeparationSettlement $employeeSeparationSettlement, User $actor, ?Request $request = null, ?string $note = null): EmployeeSeparationSettlement
    {
        return DB::transaction(function () use ($employeeSeparationSettlement, $actor, $request, $note): EmployeeSeparationSettlement {
            $settlement = EmployeeSeparationSettlement::query()->whereKey($employeeSeparationSettlement->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->whereKey($settlement->employee_id)->firstOrFail();
            $calculation = $this->calculate($employee, [
                'last_working_date' => $settlement->last_working_date?->toDateString(),
                'bonus_amount' => $settlement->bonus_amount,
                'gratuity_amount' => $settlement->gratuity_amount,
                'notice_recovery_amount' => $settlement->notice_recovery_amount,
                'tax_recovery_amount' => $settlement->tax_recovery_amount,
            ]);

            $history = $settlement->workflow_history ?? [];
            $history[] = $this->workflowEvent('hr_approved', $actor, $note ?? 'HR approved F&F calculation.');

            $settlement->forceFill([
                'status' => 'hr_approved',
                'hr_approved_by_user_id' => $actor->id,
                'hr_approved_at' => now(),
                ...$this->amountPayload($calculation),
                'calculation_breakdown' => $calculation['breakdown'],
                'clearance_blockers' => $calculation['blockers'],
                'workflow_history' => $history,
            ])->save();

            $this->notifications->sendToPermission(['finance.approve'], [
                'category' => 'hr',
                'severity' => 'info',
                'title' => 'F&F settlement ready for finance approval',
                'body' => "{$employee->name}'s F&F settlement is HR-approved.",
                'action_url' => route('hr.separation-settlements.index', ['status' => 'hr_approved'], false),
                'payload' => ['settlement_number' => $settlement->settlement_number],
            ], $actor, $settlement, $settlement->company_id);

            $this->auditLogger->record($actor, 'hr.separation_settlement.hr_approved', 'HR approved separation settlement', $settlement, ['settlement_number' => $settlement->settlement_number], $request);

            return $settlement->load($this->relations());
        });
    }

    public function financeApprove(EmployeeSeparationSettlement $employeeSeparationSettlement, User $actor, ?Request $request = null, ?string $note = null): EmployeeSeparationSettlement
    {
        return DB::transaction(function () use ($employeeSeparationSettlement, $actor, $request, $note): EmployeeSeparationSettlement {
            $settlement = EmployeeSeparationSettlement::query()->whereKey($employeeSeparationSettlement->id)->lockForUpdate()->firstOrFail();
            $history = $settlement->workflow_history ?? [];
            $history[] = $this->workflowEvent('finance_approved', $actor, $note ?? 'Finance approved F&F settlement.');

            $settlement->forceFill([
                'status' => 'finance_approved',
                'finance_approved_by_user_id' => $actor->id,
                'finance_approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record($actor, 'hr.separation_settlement.finance_approved', 'Finance approved separation settlement', $settlement, ['settlement_number' => $settlement->settlement_number, 'net_payable' => $settlement->net_payable], $request);

            return $settlement->load($this->relations());
        });
    }

    public function complete(EmployeeSeparationSettlement $employeeSeparationSettlement, array $data, User $actor, ?Request $request = null): EmployeeSeparationSettlement
    {
        return DB::transaction(function () use ($employeeSeparationSettlement, $data, $actor, $request): EmployeeSeparationSettlement {
            $settlement = EmployeeSeparationSettlement::query()->whereKey($employeeSeparationSettlement->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::query()->whereKey($settlement->employee_id)->lockForUpdate()->firstOrFail();
            $blockers = $this->blockers($employee);

            if ($blockers !== []) {
                $settlement->forceFill(['clearance_blockers' => $blockers])->save();
                throw ValidationException::withMessages(['clearance_blockers' => 'F&F completion is blocked by unresolved clearances.']);
            }

            $history = $settlement->workflow_history ?? [];
            $history[] = $this->workflowEvent('completed', $actor, $data['note'] ?? 'F&F settlement completed.');

            $settlement->forceFill([
                'status' => 'completed',
                'completed_by_user_id' => $actor->id,
                'payment_reference' => $data['payment_reference'],
                'clearance_blockers' => [],
                'completed_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $employee->forceFill(['status' => 'separated'])->save();

            if ($employee->user) {
                $this->notifications->sendToUser($employee->user, [
                    'category' => 'hr',
                    'severity' => 'success',
                    'title' => 'Full and Final settlement completed',
                    'body' => 'Your Full and Final settlement has been completed.',
                    'action_url' => route('hr.separation-settlements.index', ['employee_id' => $employee->id], false),
                    'payload' => ['settlement_number' => $settlement->settlement_number, 'net_payable' => (float) $settlement->net_payable],
                ], $actor, $settlement);
            }

            $this->auditLogger->record($actor, 'hr.separation_settlement.completed', 'Completed separation settlement', $settlement, ['settlement_number' => $settlement->settlement_number, 'payment_reference' => $settlement->payment_reference], $request);

            return $settlement->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function calculate(Employee $employee, array $data): array
    {
        $monthlyCtc = (float) ($employee->monthly_ctc ?? 0);
        $dailyRate = round($monthlyCtc / 30, 2);
        $lastWorkingDay = (int) date('j', strtotime((string) $data['last_working_date']));
        $lastSalary = round($dailyRate * $lastWorkingDay, 2);

        $encashableDays = (float) EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->whereHas('leaveType', fn ($query) => $query->where('encashment_enabled', true))
            ->sum('available_days');
        $leaveEncashment = round($dailyRate * $encashableDays, 2);

        $claimPayable = (float) ExpenseClaim::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->sum('approved_amount');

        $loanRecovery = (float) EmployeeLoan::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'disbursed'])
            ->sum('approved_amount');

        $gross = round($lastSalary + $leaveEncashment + (float) ($data['bonus_amount'] ?? 0) + (float) ($data['gratuity_amount'] ?? 0) + $claimPayable, 2);
        $recoveries = round((float) ($data['notice_recovery_amount'] ?? 0) + $loanRecovery + (float) ($data['tax_recovery_amount'] ?? 0), 2);

        return [
            'last_salary_amount' => $lastSalary,
            'leave_encashment_amount' => $leaveEncashment,
            'bonus_amount' => round((float) ($data['bonus_amount'] ?? 0), 2),
            'gratuity_amount' => round((float) ($data['gratuity_amount'] ?? 0), 2),
            'claim_payable_amount' => round($claimPayable, 2),
            'notice_recovery_amount' => round((float) ($data['notice_recovery_amount'] ?? 0), 2),
            'loan_recovery_amount' => round($loanRecovery, 2),
            'asset_recovery_amount' => 0.0,
            'tax_recovery_amount' => round((float) ($data['tax_recovery_amount'] ?? 0), 2),
            'gross_payable' => $gross,
            'total_recoveries' => $recoveries,
            'net_payable' => round($gross - $recoveries, 2),
            'blockers' => $this->blockers($employee),
            'breakdown' => [
                'daily_rate' => $dailyRate,
                'last_working_day_payable_days' => $lastWorkingDay,
                'encashable_leave_days' => $encashableDays,
                'formula' => 'last_salary + leave_encashment + bonus + gratuity + approved_claims - notice_recovery - loan_recovery - tax_recovery',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blockers(Employee $employee): array
    {
        $blockers = [];

        $assignedAssets = EmployeeAsset::query()->where('employee_id', $employee->id)->where('status', 'assigned')->count();
        if ($assignedAssets > 0) {
            $blockers[] = ['type' => 'assets', 'message' => 'Assigned assets must be recovered before completion.', 'count' => $assignedAssets];
        }

        $openLoans = EmployeeLoan::query()->where('employee_id', $employee->id)->whereIn('status', ['submitted', 'approved', 'disbursed'])->count();
        if ($openLoans > 0) {
            $blockers[] = ['type' => 'loans', 'message' => 'Open loans must be rejected, settled or recovered before completion.', 'count' => $openLoans];
        }

        $openClaims = ExpenseClaim::query()->where('employee_id', $employee->id)->whereIn('status', ['submitted', 'approved'])->count();
        if ($openClaims > 0) {
            $blockers[] = ['type' => 'claims', 'message' => 'Open expense claims must be rejected or paid before completion.', 'count' => $openClaims];
        }

        $pendingAttendance = AttendanceRegularizationRequest::query()->where('employee_id', $employee->id)->where('status', 'submitted')->count();
        if ($pendingAttendance > 0) {
            $blockers[] = ['type' => 'attendance', 'message' => 'Pending attendance regularizations must be resolved before completion.', 'count' => $pendingAttendance];
        }

        return $blockers;
    }

    /**
     * @param array<string, mixed> $calculation
     * @return array<string, mixed>
     */
    private function amountPayload(array $calculation): array
    {
        return collect($calculation)->only([
            'last_salary_amount',
            'leave_encashment_amount',
            'bonus_amount',
            'gratuity_amount',
            'claim_payable_amount',
            'notice_recovery_amount',
            'loan_recovery_amount',
            'asset_recovery_amount',
            'tax_recovery_amount',
            'gross_payable',
            'total_recoveries',
            'net_payable',
        ])->all();
    }

    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return ['status' => $status, 'actor_user_id' => $actor->id, 'actor' => $actor->name, 'note' => $note, 'at' => now()->toISOString()];
    }

    private function nextSettlementNumber(): string
    {
        return sprintf('FNF-%05d', EmployeeSeparationSettlement::query()->withTrashed()->count() + 10001);
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['employee.user', 'initiatedBy', 'hrApprovedBy', 'financeApprovedBy', 'completedBy'];
    }
}
