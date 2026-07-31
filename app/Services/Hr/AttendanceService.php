<?php

namespace App\Services\Hr;

use App\Domain\Hr\Services\RosterResolutionEngine;
use App\Models\AttendanceRecord;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RosterResolutionEngine $rosterResolution,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function createShift(array $data, User $actor, ?Request $request = null): AttendanceShift
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendanceShift {
            $companyId = (int) ($data['company_id'] ?? $actor->company_id);

            if (! app(CompanyScopeService::class)->allows($actor, $companyId)) {
                throw ValidationException::withMessages([
                    'company_id' => 'The selected company is outside your company scope.',
                ]);
            }

            $rules = array_filter($data['rules'] ?? [], fn ($value): bool => $value !== null && $value !== '');

            $shift = AttendanceShift::create([
                'company_id' => $companyId,
                'code' => strtoupper((string) $data['code']),
                'name' => $data['name'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'is_overnight' => (bool) $data['is_overnight'],
                'late_grace_minutes' => (int) $data['late_grace_minutes'],
                'early_leave_grace_minutes' => (int) $data['early_leave_grace_minutes'],
                'half_day_threshold_minutes' => (int) $data['half_day_threshold_minutes'],
                'full_day_threshold_minutes' => (int) $data['full_day_threshold_minutes'],
                'rules' => $rules ?: null,
                'is_active' => true,
            ]);

            $segments = collect((array) ($data['segments'] ?? []))
                ->sortBy(fn (array $segment): int => $this->segmentSortMinutes(
                    (string) $segment['starts_at'],
                    (string) $shift->starts_at,
                    (bool) $shift->is_overnight,
                ))
                ->values();
            foreach ($segments as $index => $segment) {
                $shift->segments()->create([
                    'sequence' => $index + 1,
                    'label' => trim((string) ($segment['label'] ?? '')) ?: null,
                    'starts_at' => $segment['starts_at'],
                    'ends_at' => $segment['ends_at'],
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'hr.attendance_shift.created',
                'Created attendance shift',
                $shift,
                [
                    'code' => $shift->code,
                    'name' => $shift->name,
                    'starts_at' => $shift->starts_at,
                    'ends_at' => $shift->ends_at,
                    'is_overnight' => $shift->is_overnight,
                    'rules' => $shift->rules ?? [],
                    'segments' => $shift->segments()->orderBy('sequence')->get(['sequence', 'label', 'starts_at', 'ends_at'])->toArray(),
                ],
                $request,
            );

            return $shift->load('segments');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitRegularization(array $data, User $actor, ?Request $request = null): AttendanceRegularizationRequest
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendanceRegularizationRequest {
            $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $employee->company_id)) {
                throw ValidationException::withMessages([
                    'employee_id' => 'The selected employee is outside your company scope.',
                ]);
            }

            $this->assertPeriodEditable((int) $employee->company_id, Carbon::parse($data['work_date']));

            $attendanceRecord = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->where('work_date', $data['work_date'])
                ->first();

            $regularization = AttendanceRegularizationRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_record_id' => $attendanceRecord?->id,
                'requested_by_user_id' => $actor->id,
                'request_number' => $this->nextRegularizationNumber(),
                'status' => 'submitted',
                'work_date' => $data['work_date'],
                'requested_check_in_at' => $data['requested_check_in_at'],
                'requested_check_out_at' => $data['requested_check_out_at'],
                'reason' => $data['reason'],
                'workflow_history' => [
                    ['status' => 'submitted', 'actor_user_id' => $actor->id, 'at' => now()->toISOString()],
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.attendance_regularization.submitted',
                'Submitted attendance regularization',
                $regularization,
                [
                    'request_number' => $regularization->request_number,
                    'employee_code' => $employee->employee_code,
                    'work_date' => $regularization->work_date->toDateString(),
                ],
                $request,
            );

            return $regularization->load($this->regularizationRelations());
        });
    }

    public function approveRegularization(
        AttendanceRegularizationRequest $regularization,
        array $data,
        User $actor,
        ?Request $request = null,
    ): AttendanceRegularizationRequest {
        return DB::transaction(function () use ($regularization, $data, $actor, $request): AttendanceRegularizationRequest {
            $locked = AttendanceRegularizationRequest::query()
                ->whereKey($regularization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['regularization' => 'Only submitted regularization requests can be approved.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $locked->company_id)) {
                throw ValidationException::withMessages([
                    'regularization' => 'The selected regularization request is outside your company scope.',
                ]);
            }


            $this->assertPeriodEditable((int) $locked->company_id, $locked->work_date);

            $employee = $locked->employee;
            $shift = $this->shiftForEmployee($employee, $locked->work_date->toDateString());
            $calculation = $this->calculate(
                $shift,
                $locked->work_date->toDateString(),
                $locked->requested_check_in_at,
                $locked->requested_check_out_at,
            );

            $attendanceRecord = AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $locked->work_date->toDateString())
                ->lockForUpdate()
                ->first();

            if (! $attendanceRecord) {
                $attendanceRecord = new AttendanceRecord([
                    'employee_id' => $employee->id,
                    'work_date' => $locked->work_date->toDateString(),
                ]);
            }

            $attendanceRecord->fill([
                'company_id' => $employee->company_id,
                'attendance_shift_id' => $shift->id,
                'check_in_at' => $locked->requested_check_in_at,
                'check_out_at' => $locked->requested_check_out_at,
                'source' => 'regularized',
                'status' => $calculation['status'],
                'late_minutes' => $calculation['late_minutes'],
                'early_leave_minutes' => $calculation['early_leave_minutes'],
                'worked_minutes' => $calculation['worked_minutes'],
                'metadata' => [
                    'regularization_request_number' => $locked->request_number,
                    'approved_by_user_id' => $actor->id,
                ],
            ])->save();

            $locked->forceFill([
                'attendance_record_id' => $attendanceRecord->id,
                'status' => 'approved',
                'decided_by_user_id' => $actor->id,
                'decision_note' => $data['decision_note'] ?? null,
                'decided_at' => now(),
                'workflow_history' => $this->appendWorkflow($locked, 'approved', $actor, $data['decision_note'] ?? null),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.attendance_regularization.approved',
                'Approved attendance regularization',
                $locked,
                [
                    'request_number' => $locked->request_number,
                    'attendance_status' => $attendanceRecord->status,
                    'late_minutes' => $attendanceRecord->late_minutes,
                    'early_leave_minutes' => $attendanceRecord->early_leave_minutes,
                    'decision_note' => $data['decision_note'] ?? null,
                ],
                $request,
            );

            return $locked->load($this->regularizationRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function rejectRegularization(
        AttendanceRegularizationRequest $regularization,
        array $data,
        User $actor,
        ?Request $request = null,
    ): AttendanceRegularizationRequest {
        return DB::transaction(function () use ($regularization, $data, $actor, $request): AttendanceRegularizationRequest {
            $locked = AttendanceRegularizationRequest::query()
                ->whereKey($regularization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'submitted') {
                throw ValidationException::withMessages(['regularization' => 'Only submitted regularization requests can be rejected.']);
            }

            if (! app(CompanyScopeService::class)->allows($actor, $locked->company_id)) {
                throw ValidationException::withMessages([
                    'regularization' => 'The selected regularization request is outside your company scope.',
                ]);
            }

            $locked->forceFill([
                'status' => 'rejected',
                'decided_by_user_id' => $actor->id,
                'decision_note' => $data['decision_note'],
                'decided_at' => now(),
                'workflow_history' => $this->appendWorkflow($locked, 'rejected', $actor, $data['decision_note']),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'hr.attendance_regularization.rejected',
                'Rejected attendance regularization',
                $locked,
                ['request_number' => $locked->request_number],
                $request,
            );

            return $locked->load($this->regularizationRelations());
        });
    }

    public function shiftForEmployee(Employee $employee, string $workDate): AttendanceShift
    {
        $resolvedSchedule = $this->rosterResolution->resolve($employee, $workDate);

        if ($resolvedSchedule instanceof AttendanceRosterEntry && $resolvedSchedule->entry_type !== 'shift') {
            throw ValidationException::withMessages([
                'employee_id' => 'The published roster marks this date as a non-working day. Reopen or correct the roster before approving attendance.',
            ]);
        }

        $shift = $resolvedSchedule?->shift;
        if (! $shift || ! $shift->is_active) {
            throw ValidationException::withMessages(['employee_id' => 'No active attendance shift is assigned for this employee and date.']);
        }

        return $shift;
    }

    /**
     * @return array{status: string, late_minutes: int, early_leave_minutes: int, worked_minutes: int}
     */
    public function calculate(AttendanceShift $shift, string $workDate, Carbon $checkInAt, Carbon $checkOutAt): array
    {
        $scheduledStart = Carbon::parse($workDate.' '.$shift->starts_at);
        $scheduledEnd = Carbon::parse($workDate.' '.$shift->ends_at);

        if ($shift->is_overnight || $scheduledEnd->lessThanOrEqualTo($scheduledStart)) {
            $scheduledEnd->addDay();
        }

        $workedMinutes = max((int) $checkInAt->diffInMinutes($checkOutAt), 0);
        $lateMinutes = max((int) $scheduledStart->diffInMinutes($checkInAt, false) - (int) $shift->late_grace_minutes, 0);
        $earlyLeaveMinutes = max((int) $checkOutAt->diffInMinutes($scheduledEnd, false) - (int) $shift->early_leave_grace_minutes, 0);

        $status = match (true) {
            $workedMinutes < (int) $shift->half_day_threshold_minutes => 'absent',
            $workedMinutes < (int) $shift->full_day_threshold_minutes => 'half_day',
            $lateMinutes > 0 => 'late',
            $earlyLeaveMinutes > 0 => 'early_leave',
            default => 'present',
        };

        return [
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeaveMinutes,
            'worked_minutes' => $workedMinutes,
        ];
    }

    private function nextRegularizationNumber(): string
    {
        return sprintf('AR-%04d', AttendanceRegularizationRequest::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function appendWorkflow(AttendanceRegularizationRequest $regularization, string $status, User $actor, ?string $note = null): array
    {
        $history = $regularization->workflow_history ?? [];
        $history[] = array_filter([
            'status' => $status,
            'actor_user_id' => $actor->id,
            'note' => $note,
            'at' => now()->toISOString(),
        ], fn ($value): bool => $value !== null);

        return $history;
    }

    /**
     * @return array<int, string>
     */
    private function regularizationRelations(): array
    {
        return ['employee', 'attendanceRecord', 'requestedBy', 'decidedBy'];
    }

    private function assertPeriodEditable(int $companyId, Carbon $workDate): void
    {
        $isFinalized = AttendancePeriodLock::query()
            ->where('company_id', $companyId)
            ->where('status', 'finalized')
            ->whereDate('period_start', '<=', $workDate->toDateString())
            ->whereDate('period_end', '>=', $workDate->toDateString())
            ->exists();

        if ($isFinalized) {
            throw ValidationException::withMessages([
                'work_date' => 'Attendance for this date is finalized. Reopen the attendance period before requesting or approving a correction.',
            ]);
        }
    }

    private function segmentSortMinutes(string $time, string $shiftStart, bool $overnight): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        [$startHours, $startMinutes] = array_map('intval', explode(':', $shiftStart));
        $value = ($hours * 60) + $minutes;
        $anchor = ($startHours * 60) + $startMinutes;

        return $overnight && $value < $anchor ? $value + 1440 : $value;
    }
}
