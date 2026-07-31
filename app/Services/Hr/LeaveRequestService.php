<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeLeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveRequestService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, User $actor, ?Request $request = null): LeaveRequest
    {
        return DB::transaction(function () use ($data, $actor, $request): LeaveRequest {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $leaveType = LeaveType::query()->whereKey($data['leave_type_id'])->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'The selected employee is outside your company scope.',
                ]);
            }

            $days = $this->calculateRequestedDays($data['starts_on'], $data['ends_on'], $data['duration_unit']);

            $balance = $this->lockedBalance($employee, $leaveType, (int) Carbon::parse($data['starts_on'])->year);

            if (! $leaveType->allow_negative_balance && (float) $balance->available_days < $days) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'Insufficient leave balance for this request.',
                ]);
            }

            $leaveRequest = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'supporting_document_id' => $data['supporting_document_id'] ?? null,
                'requested_by_user_id' => $actor->id,
                'request_number' => $this->nextRequestNumber(),
                'status' => 'submitted',
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'duration_unit' => $data['duration_unit'],
                'requested_days' => $days,
                'reason' => $data['reason'] ?? null,
                'workflow_history' => [
                    ['status' => 'submitted', 'actor_user_id' => $actor->id, 'at' => now()->toISOString()],
                ],
            ]);

            $this->reserveBalance($balance, $days, $leaveRequest, $actor);

            $this->auditLogger->record(
                $actor,
                'hr.leave.submitted',
                'Submitted leave request',
                $leaveRequest,
                [
                    'request_number' => $leaveRequest->request_number,
                    'employee_code' => $employee->employee_code,
                    'leave_type' => $leaveType->code,
                    'requested_days' => $days,
                ],
                $request,
            );

            return $leaveRequest->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(LeaveRequest $leaveRequest, array $data, User $actor, ?Request $request = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $data, $actor, $request): LeaveRequest {
            $lockedRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== 'submitted') {
                throw ValidationException::withMessages(['leave_request' => 'Only submitted leave requests can be approved.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $lockedRequest->company_id)) {
                throw ValidationException::withMessages([
                    'leave_request' => 'The selected leave request is outside your company scope.',
                ]);
            }

            $balance = $this->lockedBalance($lockedRequest->employee, $lockedRequest->leaveType, (int) $lockedRequest->starts_on->year);
            $days = (float) $lockedRequest->requested_days;

            if ((float) $balance->pending_days < $days) {
                throw ValidationException::withMessages(['leave_request' => 'Reserved leave balance is lower than the request days.']);
            }

            $lockedRequest->forceFill([
                'status' => 'approved',
                'decided_by_user_id' => $actor->id,
                'decision_note' => $data['decision_note'] ?? null,
                'decided_at' => now(),
                'workflow_history' => $this->appendWorkflow($lockedRequest, 'approved', $actor, $data['decision_note'] ?? null),
            ])->save();

            $this->approveBalance($balance, $days, $lockedRequest, $actor);

            $this->auditLogger->record(
                $actor,
                'hr.leave.approved',
                'Approved leave request',
                $lockedRequest,
                [
                    'request_number' => $lockedRequest->request_number,
                    'requested_days' => $days,
                    'decision_note' => $data['decision_note'] ?? null,
                ],
                $request,
            );

            return $lockedRequest->load($this->relations());
        });
    }

    public function reject(LeaveRequest $leaveRequest, array $data, User $actor, ?Request $request = null): LeaveRequest
    {
        return DB::transaction(function () use ($leaveRequest, $data, $actor, $request): LeaveRequest {
            $lockedRequest = LeaveRequest::query()
                ->whereKey($leaveRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== 'submitted') {
                throw ValidationException::withMessages(['leave_request' => 'Only submitted leave requests can be rejected.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $lockedRequest->company_id)) {
                throw ValidationException::withMessages([
                    'leave_request' => 'The selected leave request is outside your company scope.',
                ]);
            }

            $balance = $this->lockedBalance($lockedRequest->employee, $lockedRequest->leaveType, (int) $lockedRequest->starts_on->year);
            $days = (float) $lockedRequest->requested_days;

            $lockedRequest->forceFill([
                'status' => 'rejected',
                'decided_by_user_id' => $actor->id,
                'decision_note' => $data['decision_note'],
                'decided_at' => now(),
                'workflow_history' => $this->appendWorkflow($lockedRequest, 'rejected', $actor, $data['decision_note']),
            ])->save();

            $this->releaseBalance($balance, $days, $lockedRequest, $actor);

            $this->auditLogger->record(
                $actor,
                'hr.leave.rejected',
                'Rejected leave request',
                $lockedRequest,
                ['request_number' => $lockedRequest->request_number, 'requested_days' => $days],
                $request,
            );

            return $lockedRequest->load($this->relations());
        });
    }

    private function calculateRequestedDays(string $startsOn, string $endsOn, string $durationUnit): float
    {
        if ($durationUnit === 'half_day') {
            return 0.5;
        }

        return (float) (Carbon::parse($startsOn)->startOfDay()->diffInDays(Carbon::parse($endsOn)->startOfDay()) + 1);
    }

    private function lockedBalance(Employee $employee, LeaveType $leaveType, int $periodYear): EmployeeLeaveBalance
    {
        return EmployeeLeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('period_year', $periodYear)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function reserveBalance(EmployeeLeaveBalance $balance, float $days, LeaveRequest $leaveRequest, User $actor): void
    {
        $balance->forceFill([
            'pending_days' => (float) $balance->pending_days + $days,
            'available_days' => (float) $balance->available_days - $days,
            'ledger' => $this->appendLedger($balance, 'leave_submitted', -$days, $leaveRequest, $actor),
        ])->save();
    }

    private function approveBalance(EmployeeLeaveBalance $balance, float $days, LeaveRequest $leaveRequest, User $actor): void
    {
        $balance->forceFill([
            'pending_days' => max((float) $balance->pending_days - $days, 0),
            'used_days' => (float) $balance->used_days + $days,
            'ledger' => $this->appendLedger($balance, 'leave_approved', -$days, $leaveRequest, $actor),
        ])->save();
    }

    private function releaseBalance(EmployeeLeaveBalance $balance, float $days, LeaveRequest $leaveRequest, User $actor): void
    {
        $balance->forceFill([
            'pending_days' => max((float) $balance->pending_days - $days, 0),
            'available_days' => (float) $balance->available_days + $days,
            'ledger' => $this->appendLedger($balance, 'leave_rejected', $days, $leaveRequest, $actor),
        ])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appendLedger(EmployeeLeaveBalance $balance, string $event, float $days, LeaveRequest $leaveRequest, User $actor): array
    {
        $ledger = $balance->ledger ?? [];
        $ledger[] = [
            'event' => $event,
            'days' => $days,
            'request_number' => $leaveRequest->request_number,
            'actor_user_id' => $actor->id,
            'at' => now()->toISOString(),
        ];

        return $ledger;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appendWorkflow(LeaveRequest $leaveRequest, string $status, User $actor, ?string $note = null): array
    {
        $history = $leaveRequest->workflow_history ?? [];
        $history[] = array_filter([
            'status' => $status,
            'actor_user_id' => $actor->id,
            'note' => $note,
            'at' => now()->toISOString(),
        ], fn ($value): bool => $value !== null);

        return $history;
    }

    private function nextRequestNumber(): string
    {
        return sprintf('LV-%04d', LeaveRequest::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['employee', 'leaveType', 'requestedBy', 'decidedBy', 'supportingDocument'];
    }
}
