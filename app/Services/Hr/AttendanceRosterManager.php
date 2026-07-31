<?php

namespace App\Services\Hr;

use App\Domain\Hr\Data\AttendanceRosterRuleSet;
use App\Domain\Hr\Services\AttendancePeriodFinalizationGuard;
use App\Domain\Hr\Services\AttendanceRosterRulePackResolver;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use App\Models\AttendanceShiftSwapRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AttendanceRosterManager
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly AuditLogger $audit,
        private readonly NotificationCenterService $notifications,
        private readonly AttendanceRosterRulePackResolver $rulePacks,
        private readonly AttendancePeriodFinalizationGuard $finalizationGuard,
    ) {}

    /** @param array<string, mixed> $data */
    public function assign(array $data, User $actor, ?Request $request = null): EmployeeShiftAssignment
    {
        return DB::transaction(function () use ($data, $actor, $request): EmployeeShiftAssignment {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $shift = AttendanceShift::query()->whereKey($data['attendance_shift_id'])->firstOrFail();
            $this->assertScopedCompany($actor, (int) $employee->company_id, 'employee_id');

            if ((int) $shift->company_id !== (int) $employee->company_id || ! $shift->is_active) {
                throw ValidationException::withMessages(['attendance_shift_id' => 'Select an active shift from the employee company.']);
            }

            $from = Carbon::parse($data['effective_from'])->toDateString();
            $to = isset($data['effective_to']) && $data['effective_to'] !== null
                ? Carbon::parse($data['effective_to'])->toDateString()
                : null;

            $existing = EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('attendance_shift_id', $shift->id)
                ->where('is_active', true)
                ->whereDate('effective_from', $from)
                ->when($to === null, fn ($query) => $query->whereNull('effective_to'), fn ($query) => $query->whereDate('effective_to', $to))
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing->load(['employee', 'shift']);
            }

            $overlap = EmployeeShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->when($to, fn ($query) => $query->whereDate('effective_from', '<=', $to))
                ->where(function ($query) use ($from): void {
                    $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from);
                })
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages(['effective_from' => 'This employee already has an active shift assignment in the selected effective period.']);
            }

            $assignment = EmployeeShiftAssignment::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_shift_id' => $shift->id,
                'effective_from' => $from,
                'effective_to' => $to,
                'is_active' => true,
                'created_by_user_id' => $actor->id,
            ]);

            $this->audit->record($actor, 'hr.shift_assignment.created', 'Assigned an effective attendance shift', $assignment, [
                'employee_id' => $employee->id,
                'attendance_shift_id' => $shift->id,
                'effective_from' => $from,
                'effective_to' => $to,
            ], $request);

            $this->notifyEmployeeIds(
                [(int) $employee->id],
                $actor,
                'Attendance shift assigned',
                'Your effective attendance shift assignment has been updated.',
                'info',
                $assignment,
            );

            return $assignment->load(['employee', 'shift']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createRoster(array $data, User $actor, ?Request $request = null): AttendanceRoster
    {
        $companyId = (int) ($data['company_id'] ?? $this->companyScope->companyIdFor($actor));
        $this->assertScopedCompany($actor, $companyId, 'company_id');

        return DB::transaction(function () use ($data, $actor, $request, $companyId): AttendanceRoster {
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $name = trim((string) $data['name']);
            $start = Carbon::parse($data['period_start'])->toDateString();
            $end = Carbon::parse($data['period_end'])->toDateString();
            $rules = $this->rulePacks->resolve($companyId, $start);
            $timezone = $rules->timezone;
            $existing = AttendanceRoster::query()
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->where('timezone', $timezone)
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $roster = AttendanceRoster::create([
                'company_id' => $companyId,
                'name' => $name,
                'period_start' => $start,
                'period_end' => $end,
                'timezone' => $timezone,
                'status' => 'draft',
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);

            $this->audit->record($actor, 'hr.attendance_roster.created', 'Created attendance roster draft', $roster, [
                'period_start' => $roster->period_start->toDateString(),
                'period_end' => $roster->period_end->toDateString(),
                'timezone' => $roster->timezone,
            ], $request);

            return $roster;
        });
    }

    /** @param array<string, mixed> $data */
    public function createEntry(AttendanceRoster $roster, array $data, User $actor, ?Request $request = null): AttendanceRosterEntry
    {
        return DB::transaction(function () use ($roster, $data, $actor, $request): AttendanceRosterEntry {
            $lockedRoster = AttendanceRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $lockedRoster->company_id, 'attendance_roster');
            $this->assertVersion($lockedRoster, (int) $data['lock_version'], 'attendance_roster');
            $this->assertRosterDraft($lockedRoster);

            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->assertEmployeeInRoster($employee, $lockedRoster);

            $workDate = Carbon::parse($data['work_date']);
            if ($workDate->lt($lockedRoster->period_start) || $workDate->gt($lockedRoster->period_end)) {
                throw ValidationException::withMessages(['work_date' => 'The work date must be within the roster period.']);
            }

            if (AttendanceRosterEntry::query()->where('attendance_roster_id', $lockedRoster->id)->where('employee_id', $employee->id)->whereDate('work_date', $workDate)->exists()) {
                throw ValidationException::withMessages(['work_date' => 'This employee already has an entry for the selected roster date.']);
            }

            $entryType = $data['entry_type'] ?? 'shift';
            $shift = $entryType === 'shift'
                ? AttendanceShift::query()->whereKey($data['attendance_shift_id'])->firstOrFail()
                : null;
            if ($shift && ((int) $shift->company_id !== (int) $lockedRoster->company_id || ! $shift->is_active)) {
                throw ValidationException::withMessages(['attendance_shift_id' => 'Select an active shift from the roster company.']);
            }

            [$startsAt, $endsAt] = $shift
                ? $this->shiftInstants($workDate->toDateString(), $shift, $lockedRoster->timezone)
                : [null, null];

            $entry = AttendanceRosterEntry::create([
                'attendance_roster_id' => $lockedRoster->id,
                'company_id' => $lockedRoster->company_id,
                'employee_id' => $employee->id,
                'attendance_shift_id' => $shift?->id,
                'work_date' => $workDate->toDateString(),
                'entry_type' => $entryType,
                'source' => 'manual',
                'occurrence_key' => sprintf('roster:%d:employee:%d:%s', $lockedRoster->id, $employee->id, $workDate->format('Ymd')),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'metadata' => ['created_by_user_id' => $actor->id],
                'lock_version' => 1,
            ]);

            $lockedRoster->increment('lock_version');
            $this->audit->record($actor, 'hr.attendance_roster.entry_created', 'Added attendance roster entry', $entry, [
                'roster_id' => $lockedRoster->id,
                'employee_id' => $employee->id,
                'work_date' => $workDate->toDateString(),
                'entry_type' => $entryType,
            ], $request);

            return $entry->load(['employee', 'shift']);
        });
    }

    public function publish(AttendanceRoster $roster, int $lockVersion, User $actor, ?string $note = null, ?Request $request = null): AttendanceRoster
    {
        return $this->transitionRoster($roster, $lockVersion, $actor, 'draft', 'published', $note, $request);
    }

    public function lock(AttendanceRoster $roster, int $lockVersion, User $actor, ?string $note = null, ?Request $request = null): AttendanceRoster
    {
        return $this->transitionRoster($roster, $lockVersion, $actor, 'published', 'locked', $note, $request);
    }

    public function reopenRoster(AttendanceRoster $roster, int $lockVersion, User $actor, string $reason, ?Request $request = null): AttendanceRoster
    {
        return DB::transaction(function () use ($roster, $lockVersion, $actor, $reason, $request): AttendanceRoster {
            $locked = AttendanceRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $locked->company_id, 'attendance_roster');
            $this->assertVersion($locked, $lockVersion, 'attendance_roster');

            if ($locked->status !== 'locked') {
                throw ValidationException::withMessages(['attendance_roster' => 'Only a locked roster can be reopened.']);
            }

            [$reopenLimitDays, $timezone] = $this->pinnedReopenWindow(
                $locked,
                'roster_reopen_limit_days',
                'attendance_roster',
            );
            $this->assertWithinReopenWindow(
                $locked->period_end,
                $reopenLimitDays,
                $timezone,
                'attendance_roster',
                'The governed roster reopen window has expired.',
            );

            $finalizedPeriodExists = AttendancePeriodLock::query()
                ->where('company_id', $locked->company_id)
                ->where('status', 'finalized')
                ->whereDate('period_start', '<=', $locked->period_end)
                ->whereDate('period_end', '>=', $locked->period_start)
                ->lockForUpdate()
                ->exists();
            if ($finalizedPeriodExists) {
                throw ValidationException::withMessages([
                    'attendance_roster' => 'Reopen the finalized attendance period before reopening this roster.',
                ]);
            }

            $approvedPayrollExists = PayrollRun::query()
                ->where('company_id', $locked->company_id)
                ->where('status', 'approved')
                ->whereDate('period_start', '<=', $locked->period_end)
                ->whereDate('period_end', '>=', $locked->period_start)
                ->lockForUpdate()
                ->exists();
            if ($approvedPayrollExists) {
                throw ValidationException::withMessages([
                    'attendance_roster' => 'An approved payroll run covers this roster period. Use an adjustment workflow instead of reopening the roster.',
                ]);
            }

            $locked->forceFill([
                'status' => 'published',
                'locked_by_user_id' => null,
                'locked_at' => null,
                'status_note' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->record($actor, 'hr.attendance_roster.reopened', 'Reopened locked attendance roster', $locked, [
                'reason' => $reason,
            ], $request);

            $this->notifyRosterEmployees(
                $locked,
                $actor,
                'Attendance roster reopened',
                'A locked attendance roster affecting your schedule was reopened for governed correction.',
                'warning',
            );

            return $locked;
        });
    }

    public function cancel(AttendanceRoster $roster, int $lockVersion, User $actor, string $note, ?Request $request = null): AttendanceRoster
    {
        return DB::transaction(function () use ($roster, $lockVersion, $actor, $note, $request): AttendanceRoster {
            $locked = AttendanceRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $locked->company_id, 'attendance_roster');
            $this->assertVersion($locked, $lockVersion, 'attendance_roster');

            if (! in_array($locked->status, ['draft', 'published'], true)) {
                throw ValidationException::withMessages(['attendance_roster' => 'Only draft or published rosters can be cancelled.']);
            }

            $wasPublished = $locked->status === 'published';
            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'status_note' => $note,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->record($actor, 'hr.attendance_roster.cancelled', 'Cancelled attendance roster', $locked, ['reason' => $note], $request);

            if ($wasPublished) {
                $this->notifyRosterEmployees(
                    $locked,
                    $actor,
                    'Published roster cancelled',
                    'A published attendance roster affecting your schedule has been cancelled. Review your current schedule.',
                    'warning',
                );
            }

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function createRotation(array $data, User $actor, ?Request $request = null): AttendanceRotationRule
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendanceRotationRule {
            $employee = Employee::query()->whereKey($data['employee_id'])->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $employee->company_id, 'employee_id');
            $rules = $this->rulePacks->resolve((int) $employee->company_id, $data['anchor_date']);
            if ((int) $data['generation_horizon_days'] > $rules->maximumRotationGenerationHorizonDays) {
                throw ValidationException::withMessages([
                    'generation_horizon_days' => 'The generation horizon may not exceed the governed maximum of '.$rules->maximumRotationGenerationHorizonDays.' days.',
                ]);
            }

            $pattern = array_values($data['pattern']);
            if (count($pattern) !== (int) $data['cycle_days']) {
                throw ValidationException::withMessages(['pattern' => 'The rotation pattern must contain exactly one item for each cycle day.']);
            }

            foreach ($pattern as $index => $item) {
                $type = (string) ($item['type'] ?? '');
                if (in_array($type, ['off', 'holiday'], true)) {
                    $pattern[$index] = ['type' => $type, 'attendance_shift_id' => null];
                    continue;
                }

                $shift = AttendanceShift::query()->whereKey($item['attendance_shift_id'] ?? null)->first();
                if (! $shift || (int) $shift->company_id !== (int) $employee->company_id || ! $shift->is_active) {
                    throw ValidationException::withMessages(["pattern.$index.attendance_shift_id" => 'Select an active shift from the employee company.']);
                }

                $pattern[$index] = ['type' => 'shift', 'attendance_shift_id' => $shift->id];
            }

            $existing = AttendanceRotationRule::query()
                ->where('company_id', $employee->company_id)
                ->where('employee_id', $employee->id)
                ->where('name', trim((string) $data['name']))
                ->whereDate('anchor_date', Carbon::parse($data['anchor_date'])->toDateString())
                ->where('cycle_days', (int) $data['cycle_days'])
                ->where('generation_horizon_days', (int) ($data['generation_horizon_days'] ?? 90))
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->first(fn (AttendanceRotationRule $candidate): bool => $candidate->pattern === $pattern);
            if ($existing) {
                $this->assertPinnedRotationContext($existing, true);

                return $existing;
            }

            $rule = AttendanceRotationRule::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'name' => trim((string) $data['name']),
                'anchor_date' => $data['anchor_date'],
                'cycle_days' => $data['cycle_days'],
                'pattern' => $pattern,
                'generation_horizon_days' => $data['generation_horizon_days'] ?? 90,
                'rule_context' => [
                    'pinned_at' => now()->toISOString(),
                    'packs' => $rules->ruleContext,
                    'effective_values' => $rules->effectiveRosterValues(),
                ],
                'status' => 'active',
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);

            $this->audit->record($actor, 'hr.attendance_rotation.created', 'Created attendance rotation rule', $rule, [
                'employee_id' => $employee->id,
                'anchor_date' => $rule->anchor_date->toDateString(),
                'cycle_days' => $rule->cycle_days,
            ], $request);

            return $rule;
        });
    }

    public function generateRotation(
        AttendanceRotationRule $rotation,
        AttendanceRoster $roster,
        int $lockVersion,
        User $actor,
        mixed $requestedStart = null,
        mixed $requestedEnd = null,
        ?Request $request = null,
    ): int
    {
        return DB::transaction(function () use ($rotation, $roster, $lockVersion, $actor, $requestedStart, $requestedEnd, $request): int {
            $rule = AttendanceRotationRule::query()->whereKey($rotation->id)->lockForUpdate()->firstOrFail();
            $lockedRoster = AttendanceRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $rule->company_id, 'attendance_rotation_rule');
            $this->assertVersion($lockedRoster, $lockVersion, 'attendance_roster');
            $this->assertRosterDraft($lockedRoster);

            if ($rule->status !== 'active' || (int) $rule->company_id !== (int) $lockedRoster->company_id) {
                throw ValidationException::withMessages(['attendance_rotation_rule' => 'The active rotation and roster must belong to the same company.']);
            }
            $this->assertPinnedRotationContext($rule);

            $employee = $rule->employee()->firstOrFail();
            $this->assertEmployeeInRoster($employee, $lockedRoster);
            $pattern = array_values($rule->pattern ?? []);
            $generated = 0;
            $date = $requestedStart
                ? Carbon::parse($requestedStart)->startOfDay()
                : $lockedRoster->period_start->copy()->startOfDay();
            $requestedEndDate = $requestedEnd
                ? Carbon::parse($requestedEnd)->startOfDay()
                : $lockedRoster->period_end->copy()->startOfDay();
            $end = $lockedRoster->period_end->copy()->startOfDay()
                ->min($requestedEndDate)
                ->min($date->copy()->addDays($rule->generation_horizon_days - 1));
            $generationStart = $date->copy();

            while ($date->lte($end)) {
                $offset = (int) $rule->anchor_date->diffInDays($date, false);
                $index = (($offset % $rule->cycle_days) + $rule->cycle_days) % $rule->cycle_days;
                $item = $pattern[$index];

                if (! AttendanceRosterEntry::query()->where('attendance_roster_id', $lockedRoster->id)->where('employee_id', $employee->id)->whereDate('work_date', $date)->exists()) {
                    $shift = ($item['type'] ?? null) === 'shift'
                        ? AttendanceShift::query()->whereKey($item['attendance_shift_id'])->first()
                        : null;
                    [$startsAt, $endsAt] = $shift
                        ? $this->shiftInstants($date->toDateString(), $shift, $lockedRoster->timezone)
                        : [null, null];

                    $entry = AttendanceRosterEntry::firstOrCreate(
                        ['occurrence_key' => sprintf('rotation:%d:roster:%d:%s', $rule->id, $lockedRoster->id, $date->format('Ymd'))],
                        [
                            'attendance_roster_id' => $lockedRoster->id,
                            'company_id' => $lockedRoster->company_id,
                            'employee_id' => $employee->id,
                            'attendance_shift_id' => $shift?->id,
                            'attendance_rotation_rule_id' => $rule->id,
                            'work_date' => $date->toDateString(),
                            'entry_type' => $shift ? 'shift' : (string) ($item['type'] ?? 'off'),
                            'source' => 'rotation',
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'metadata' => [
                                'cycle_index' => $index,
                                'generated_by_user_id' => $actor->id,
                                'rotation_rule_context' => $rule->rule_context,
                            ],
                            'lock_version' => 1,
                        ],
                    );

                    if ($entry->wasRecentlyCreated) {
                        $generated++;
                    }
                }

                $date->addDay();
            }

            if ($generated > 0) {
                $lockedRoster->increment('lock_version');
            }

            $this->audit->record($actor, 'hr.attendance_rotation.generated', 'Generated attendance rotation occurrences', $rule, [
                'roster_id' => $lockedRoster->id,
                'range_start' => $generationStart->toDateString(),
                'range_end' => $end->toDateString(),
                'generated_count' => $generated,
            ], $request);

            return $generated;
        });
    }

    /** @param array<string, mixed> $data */
    public function requestSwap(array $data, User $actor, ?Request $request = null): AttendanceShiftSwapRequest
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendanceShiftSwapRequest {
            $source = AttendanceRosterEntry::query()->with('roster')->whereKey($data['source_roster_entry_id'])->lockForUpdate()->firstOrFail();
            $target = AttendanceRosterEntry::query()->with('roster')->whereKey($data['target_roster_entry_id'])->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $source->company_id, 'source_roster_entry_id');
            $this->assertVersion($source, (int) $data['source_entry_lock_version'], 'source_entry_lock_version');
            $this->assertVersion($target, (int) $data['target_entry_lock_version'], 'target_entry_lock_version');

            if ((int) $source->company_id !== (int) $target->company_id || $source->id === $target->id) {
                throw ValidationException::withMessages(['target_roster_entry_id' => 'Select a different published roster entry from the same company.']);
            }

            if ((int) $source->employee_id === (int) $target->employee_id) {
                throw ValidationException::withMessages(['target_roster_entry_id' => 'Select a roster entry assigned to a different employee.']);
            }

            $requester = $actor->employee;
            if (! $requester || (
                (int) $requester->id !== (int) $source->employee_id
                && ! $actor->hasPermission('attendance.manage')
                && ! $actor->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)
            )) {
                throw ValidationException::withMessages(['source_roster_entry_id' => 'You may request a swap only for your own published roster entry.']);
            }

            if ($source->roster?->status !== 'published' || $target->roster?->status !== 'published') {
                throw ValidationException::withMessages(['source_roster_entry_id' => 'Both roster entries must be published and unlocked.']);
            }

            if ($source->entry_type !== 'shift' || $target->entry_type !== 'shift') {
                throw ValidationException::withMessages(['target_roster_entry_id' => 'Only scheduled shift entries can be swapped.']);
            }

            $this->assertSwapCutoff($source, 'source_roster_entry_id');
            $this->assertSwapCutoff($target, 'target_roster_entry_id');

            $duplicate = AttendanceShiftSwapRequest::query()
                ->where('status', 'submitted')
                ->where(function ($query) use ($source, $target): void {
                    $query->whereIn('source_roster_entry_id', [$source->id, $target->id])
                        ->orWhereIn('target_roster_entry_id', [$source->id, $target->id]);
                })
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['source_roster_entry_id' => 'One of the selected entries already has a pending swap request.']);
            }

            $swap = AttendanceShiftSwapRequest::create([
                'company_id' => $source->company_id,
                'requester_employee_id' => $source->employee_id,
                'source_roster_entry_id' => $source->id,
                'target_roster_entry_id' => $target->id,
                'requested_by_user_id' => $actor->id,
                'request_number' => $this->nextSwapNumber(),
                'status' => 'submitted',
                'reason' => trim((string) $data['reason']),
                'lock_version' => 1,
            ]);

            $this->audit->record($actor, 'hr.attendance_swap.submitted', 'Submitted attendance shift swap request', $swap, [
                'source_entry_id' => $source->id,
                'target_entry_id' => $target->id,
            ], $request);

            $this->notifications->sendToPermission(['attendance.approve', LogicCenterPermissions::SWAP_APPROVE], [
                'category' => 'hr',
                'severity' => 'warning',
                'title' => 'Shift swap approval required',
                'body' => 'A published attendance shift swap is waiting for an independent decision.',
                'action_url' => '/hr/attendance-rosters?view=swaps',
                'payload' => [
                    'swap_request_id' => $swap->id,
                    'request_number' => $swap->request_number,
                    'status' => $swap->status,
                ],
            ], $actor, $swap, (int) $swap->company_id);

            return $swap->load(['requesterEmployee', 'sourceEntry.employee', 'targetEntry.employee']);
        });
    }

    public function approveSwap(AttendanceShiftSwapRequest $swap, int $lockVersion, User $actor, ?string $note = null, ?Request $request = null): AttendanceShiftSwapRequest
    {
        return DB::transaction(function () use ($swap, $lockVersion, $actor, $note, $request): AttendanceShiftSwapRequest {
            $locked = AttendanceShiftSwapRequest::query()->whereKey($swap->id)->lockForUpdate()->firstOrFail();
            $this->assertSwapDecision($locked, $lockVersion, $actor);

            $source = AttendanceRosterEntry::query()->with('roster')->whereKey($locked->source_roster_entry_id)->lockForUpdate()->firstOrFail();
            $target = AttendanceRosterEntry::query()->with('roster')->whereKey($locked->target_roster_entry_id)->lockForUpdate()->firstOrFail();
            if ($source->roster?->status !== 'published' || $target->roster?->status !== 'published') {
                throw ValidationException::withMessages(['attendance_shift_swap_request' => 'The roster changed and this swap can no longer be approved.']);
            }

            $sourceEmployeeId = (int) $source->employee_id;
            $targetEmployeeId = (int) $target->employee_id;
            $this->assertNoSwapConflict($source, $target, $targetEmployeeId, $sourceEmployeeId);

            $sourceWorkDate = $source->work_date->toDateString();
            $targetWorkDate = $target->work_date->toDateString();
            $sameRosterDate = (int) $source->attendance_roster_id === (int) $target->attendance_roster_id
                && $sourceWorkDate === $targetWorkDate;

            // Release both unique identities before assigning their final employees. On a
            // same-roster/same-date swap, updating either employee first would otherwise
            // violate attendance_roster_employee_date_unique midway through the transaction.
            $target->forceFill([
                'work_date' => $sameRosterDate ? $this->temporarySwapDate($target) : $targetWorkDate,
                'occurrence_key' => sprintf('swap-staging:%d:%d', $locked->id, $target->id),
            ])->save();

            $source->forceFill([
                'employee_id' => $targetEmployeeId,
                'occurrence_key' => $this->occurrenceKeyFor($source, $targetEmployeeId),
                'source' => 'swap',
                'metadata' => array_merge($source->metadata ?? [], ['swap_request_id' => $locked->id]),
                'lock_version' => $source->lock_version + 1,
            ])->save();
            $target->forceFill([
                'employee_id' => $sourceEmployeeId,
                'work_date' => $targetWorkDate,
                'occurrence_key' => $this->occurrenceKeyFor($target, $sourceEmployeeId, $targetWorkDate),
                'source' => 'swap',
                'metadata' => array_merge($target->metadata ?? [], ['swap_request_id' => $locked->id]),
                'lock_version' => $target->lock_version + 1,
            ])->save();

            $this->decideSwap($locked, 'approved', $actor, $note);
            $this->audit->record($actor, 'hr.attendance_swap.approved', 'Approved attendance shift swap', $locked, [
                'source_entry_id' => $source->id,
                'target_entry_id' => $target->id,
            ], $request);

            $this->notifySwapParticipants($locked, $actor, 'Shift swap approved', 'Your published attendance schedule has been updated by an approved shift swap.', 'success');

            return $locked->load(['requesterEmployee', 'sourceEntry.employee', 'targetEntry.employee', 'decidedBy']);
        });
    }

    public function rejectSwap(AttendanceShiftSwapRequest $swap, int $lockVersion, User $actor, string $note, ?Request $request = null): AttendanceShiftSwapRequest
    {
        return DB::transaction(function () use ($swap, $lockVersion, $actor, $note, $request): AttendanceShiftSwapRequest {
            $locked = AttendanceShiftSwapRequest::query()->whereKey($swap->id)->lockForUpdate()->firstOrFail();
            $this->assertSwapDecision($locked, $lockVersion, $actor);
            $this->decideSwap($locked, 'rejected', $actor, $note);
            $this->audit->record($actor, 'hr.attendance_swap.rejected', 'Rejected attendance shift swap', $locked, ['reason' => $note], $request);

            $this->notifySwapParticipants($locked, $actor, 'Shift swap rejected', 'The requested attendance shift swap was rejected. Review the decision in your roster workspace.', 'warning');

            return $locked;
        });
    }

    public function cancelSwap(AttendanceShiftSwapRequest $swap, int $lockVersion, User $actor, string $note, ?Request $request = null): AttendanceShiftSwapRequest
    {
        return DB::transaction(function () use ($swap, $lockVersion, $actor, $note, $request): AttendanceShiftSwapRequest {
            $locked = AttendanceShiftSwapRequest::query()->whereKey($swap->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $locked->company_id, 'attendance_shift_swap_request');
            $this->assertVersion($locked, $lockVersion, 'attendance_shift_swap_request');
            if ($locked->status !== 'submitted' || (
                (int) $locked->requested_by_user_id !== (int) $actor->id
                && ! $actor->hasPermission('attendance.manage')
                && ! $actor->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)
            )) {
                throw ValidationException::withMessages(['attendance_shift_swap_request' => 'Only the requester or attendance manager may cancel a pending swap.']);
            }

            $this->decideSwap($locked, 'cancelled', $actor, $note);
            $this->audit->record($actor, 'hr.attendance_swap.cancelled', 'Cancelled attendance shift swap', $locked, ['reason' => $note], $request);

            $this->notifySwapParticipants($locked, $actor, 'Shift swap cancelled', 'The pending attendance shift swap was cancelled.', 'info');

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function finalizePeriod(array $data, User $actor, ?Request $request = null): AttendancePeriodLock
    {
        return DB::transaction(function () use ($data, $actor, $request): AttendancePeriodLock {
            $companyId = (int) ($data['company_id'] ?? $this->companyScope->companyIdFor($actor));
            $this->assertScopedCompany($actor, $companyId, 'company_id');
            Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($data['period_start'])->startOfDay();
            $end = Carbon::parse($data['period_end'])->startOfDay();

            $activeLock = AttendancePeriodLock::query()
                ->where('company_id', $companyId)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->where('status', 'finalized')
                ->lockForUpdate()
                ->first();
            if ($activeLock) {
                return $activeLock->load('snapshots');
            }

            $pendingRegularizations = AttendanceRegularizationRequest::query()
                ->where('company_id', $companyId)
                ->where('status', 'submitted')
                ->whereDate('work_date', '>=', $start->toDateString())
                ->whereDate('work_date', '<=', $end->toDateString())
                ->exists();
            if ($pendingRegularizations) {
                throw ValidationException::withMessages(['period_start' => 'Resolve all submitted regularizations before finalizing attendance.']);
            }

            $this->finalizationGuard->reconcileAndAssert($companyId, $start, $end);

            $version = ((int) AttendancePeriodLock::query()
                ->where('company_id', $companyId)
                ->whereDate('period_start', $start)
                ->whereDate('period_end', $end)
                ->max('version')) + 1;
            $sourceHash = $this->periodSourceHash($companyId, $start, $end);
            $rules = $this->rulePacks->resolve($companyId, $start);

            $periodLock = AttendancePeriodLock::create([
                'company_id' => $companyId,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'version' => $version,
                'status' => 'finalized',
                'finalized_by_user_id' => $actor->id,
                'finalized_at' => now(),
                'source_hash' => $sourceHash,
                'rule_context' => [
                    'pinned_at' => now()->toISOString(),
                    'packs' => $rules->ruleContext,
                    'effective_values' => $rules->effectiveRosterValues(),
                ],
                'lock_version' => 1,
            ]);

            Employee::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($start, $end): void {
                    $query->where('status', 'active')
                        ->orWhereHas('attendanceRecords', fn ($records) => $records
                            ->whereDate('work_date', '>=', $start->toDateString())
                            ->whereDate('work_date', '<=', $end->toDateString()))
                        ->orWhereHas('rosterEntries', fn ($entries) => $entries
                            ->whereDate('work_date', '>=', $start->toDateString())
                            ->whereDate('work_date', '<=', $end->toDateString())
                            ->whereHas('roster', fn ($roster) => $roster->whereIn('status', ['published', 'locked'])));
                })
                ->orderBy('id')
                ->each(
                fn (Employee $employee) => $this->createAttendanceSnapshot($periodLock, $employee, $start, $end),
                );

            $this->audit->record($actor, 'hr.attendance_period.finalized', 'Finalized attendance period', $periodLock, [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'version' => $version,
                'snapshot_count' => $periodLock->snapshots()->count(),
                'source_hash' => $sourceHash,
            ], $request);

            $this->notifications->sendToPermission(['payroll.manage'], [
                'category' => 'hr',
                'severity' => 'success',
                'title' => 'Attendance period finalized',
                'body' => 'Immutable payable-day snapshots are available for payroll processing.',
                'action_url' => '/hr/attendance-rosters?view=periods',
                'payload' => [
                    'attendance_period_lock_id' => $periodLock->id,
                    'period_start' => $periodLock->period_start->toDateString(),
                    'period_end' => $periodLock->period_end->toDateString(),
                    'version' => $periodLock->version,
                ],
            ], $actor, $periodLock, $companyId);

            return $periodLock->load('snapshots');
        });
    }

    public function reopenPeriod(AttendancePeriodLock $periodLock, int $lockVersion, User $actor, string $reason, ?Request $request = null): AttendancePeriodLock
    {
        return DB::transaction(function () use ($periodLock, $lockVersion, $actor, $reason, $request): AttendancePeriodLock {
            $locked = AttendancePeriodLock::query()->whereKey($periodLock->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $locked->company_id, 'attendance_period_lock');
            $this->assertVersion($locked, $lockVersion, 'attendance_period_lock');
            if ($locked->status !== 'finalized') {
                throw ValidationException::withMessages(['attendance_period_lock' => 'Only a finalized attendance period can be reopened.']);
            }

            [$reopenLimitDays, $timezone] = $this->pinnedReopenWindow(
                $locked,
                'attendance_reopen_limit_days',
                'attendance_period_lock',
            );
            $this->assertWithinReopenWindow(
                $locked->period_end,
                $reopenLimitDays,
                $timezone,
                'attendance_period_lock',
                'The governed attendance reopen window has expired.',
            );

            $payrollRuns = PayrollRun::query()
                ->where('company_id', $locked->company_id)
                ->whereDate('period_start', $locked->period_start)
                ->whereDate('period_end', $locked->period_end)
                ->lockForUpdate()
                ->get();
            if ($payrollRuns->contains(fn (PayrollRun $run) => $run->status === 'approved')) {
                throw ValidationException::withMessages(['attendance_period_lock' => 'Attendance cannot be reopened after payroll approval. Use an authorized adjustment or reversal run.']);
            }

            foreach ($payrollRuns as $payrollRun) {
                $payrollRun->forceFill(['metadata' => array_merge($payrollRun->metadata ?? [], [
                    'attendance_snapshot_stale' => true,
                    'attendance_reopened_at' => now()->toISOString(),
                ])])->save();
            }

            $locked->forceFill([
                'status' => 'reopened',
                'reopened_by_user_id' => $actor->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->record($actor, 'hr.attendance_period.reopened', 'Reopened attendance period', $locked, [
                'reason' => $reason,
                'version' => $locked->version,
            ], $request);

            $this->notifications->sendToPermission(['payroll.manage'], [
                'category' => 'hr',
                'severity' => 'warning',
                'title' => 'Attendance period reopened',
                'body' => 'A finalized attendance period was reopened. Any unapproved payroll generated from it is now stale.',
                'action_url' => '/hr/attendance-rosters?view=periods',
                'payload' => [
                    'attendance_period_lock_id' => $locked->id,
                    'version' => $locked->version,
                    'status' => $locked->status,
                ],
            ], $actor, $locked, (int) $locked->company_id);

            return $locked;
        });
    }

    private function transitionRoster(AttendanceRoster $roster, int $lockVersion, User $actor, string $from, string $to, ?string $note, ?Request $request): AttendanceRoster
    {
        return DB::transaction(function () use ($roster, $lockVersion, $actor, $from, $to, $note, $request): AttendanceRoster {
            $locked = AttendanceRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $this->assertScopedCompany($actor, (int) $locked->company_id, 'attendance_roster');
            $this->assertVersion($locked, $lockVersion, 'attendance_roster');
            if ($locked->status !== $from) {
                throw ValidationException::withMessages(['attendance_roster' => "Only {$from} rosters can be {$to}."]);
            }

            $rules = null;
            if ($to === 'published') {
                $rules = $this->rulePacks->resolve((int) $locked->company_id, $locked->period_start);
                $this->assertPublicationLead($locked, $rules);
                $this->validateRosterForPublication($locked, $rules);
            }

            $actorColumn = $to === 'published' ? 'published_by_user_id' : 'locked_by_user_id';
            $timeColumn = $to === 'published' ? 'published_at' : 'locked_at';
            $locked->forceFill([
                'status' => $to,
                $actorColumn => $actor->id,
                $timeColumn => now(),
                'status_note' => $note,
                'rule_context' => $to === 'published' ? [
                    'pinned_at' => now()->toISOString(),
                    'packs' => $rules->ruleContext,
                    'effective_values' => $rules->effectiveRosterValues(),
                ] : $locked->rule_context,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $this->audit->record($actor, "hr.attendance_roster.{$to}", ucfirst($to).' attendance roster', $locked, ['note' => $note], $request);

            if (in_array($to, ['published', 'locked'], true)) {
                $this->notifyRosterEmployees(
                    $locked,
                    $actor,
                    $to === 'published' ? 'Attendance roster published' : 'Attendance roster locked',
                    $to === 'published'
                        ? 'Your attendance schedule has been published. Review the dated roster before your next shift.'
                        : 'Your published attendance schedule has been locked for payroll-safe processing.',
                    $to === 'published' ? 'info' : 'success',
                );
            }

            return $locked->loadCount('entries');
        });
    }

    private function validateRosterForPublication(AttendanceRoster $roster, AttendanceRosterRuleSet $rules): void
    {
        $entries = $roster->entries()->with(['employee', 'shift'])->get();
        if ($entries->isEmpty()) {
            throw ValidationException::withMessages(['attendance_roster' => 'Add at least one roster entry before publication.']);
        }

        if ($roster->timezone !== $rules->timezone) {
            throw ValidationException::withMessages([
                'attendance_roster' => 'The roster timezone does not match the active governed company timezone ('.$rules->timezone.').',
            ]);
        }

        $duplicate = $entries
            ->groupBy(fn (AttendanceRosterEntry $entry): string => $entry->employee_id.'|'.$entry->work_date->toDateString())
            ->first(fn ($group): bool => $group->count() > 1);
        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'attendance_roster' => 'Each employee may have only one roster assignment for a work date.',
            ]);
        }

        foreach ($entries as $entry) {
            if (! $entry->employee || $entry->employee->status !== 'active') {
                throw ValidationException::withMessages(['attendance_roster' => 'Remove entries for inactive or unavailable employees before publication.']);
            }
            if ($entry->entry_type === 'shift' && (! $entry->shift || ! $entry->shift->is_active)) {
                throw ValidationException::withMessages(['attendance_roster' => 'Every shift entry must reference an active shift.']);
            }

            if ($entry->entry_type === 'shift' && $entry->starts_at && $entry->ends_at) {
                $inRosterConflict = $entries->contains(fn (AttendanceRosterEntry $other): bool =>
                    $other->id !== $entry->id
                    && (int) $other->employee_id === (int) $entry->employee_id
                    && $other->entry_type === 'shift'
                    && $other->starts_at
                    && $other->ends_at
                    && $other->starts_at->lt($entry->ends_at)
                    && $other->ends_at->gt($entry->starts_at));
                if ($rules->blockShiftOverlaps && ($inRosterConflict || $this->authoritativeEntryConflictExists($entry->employee_id, $entry, [$entry->id]))) {
                    throw ValidationException::withMessages(['attendance_roster' => 'An employee has overlapping authoritative roster shifts around '.$entry->work_date->toDateString().'.']);
                }
            } elseif ($rules->blockShiftOverlaps && $this->authoritativeEntryConflictExists($entry->employee_id, $entry, [$entry->id])) {
                throw ValidationException::withMessages(['attendance_roster' => 'An employee already has an authoritative roster entry on '.$entry->work_date->toDateString().'.']);
            }
        }

        $this->assertMinimumRest($roster, $entries, $rules->minimumRestMinutes);
        $this->assertMaximumConsecutiveWorkdays($roster, $entries, $rules->maximumConsecutiveWorkdays);
        $this->assertCompletePeriodAssignment($roster, $entries, $rules);
    }

    /** @param Collection<int, AttendanceRosterEntry> $entries */
    private function assertMinimumRest(AttendanceRoster $roster, Collection $entries, int $minimumRestMinutes): void
    {
        if ($minimumRestMinutes <= 0) {
            return;
        }

        foreach ($entries->pluck('employee_id')->unique() as $employeeId) {
            $candidateEntries = $entries
                ->where('employee_id', $employeeId)
                ->where('entry_type', 'shift')
                ->filter(fn (AttendanceRosterEntry $entry): bool => $entry->starts_at !== null && $entry->ends_at !== null);
            $authoritative = AttendanceRosterEntry::query()
                ->where('company_id', $roster->company_id)
                ->where('employee_id', $employeeId)
                ->where('attendance_roster_id', '!=', $roster->id)
                ->where('entry_type', 'shift')
                ->whereNotNull('starts_at')
                ->whereNotNull('ends_at')
                ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
                ->where('starts_at', '<', $roster->period_end->copy()->addDays(2)->endOfDay())
                ->where('ends_at', '>', $roster->period_start->copy()->subDays(2)->startOfDay())
                ->get();
            $timeline = $candidateEntries->concat($authoritative)->sortBy('starts_at')->values();

            for ($index = 1; $index < $timeline->count(); $index++) {
                $previous = $timeline[$index - 1];
                $current = $timeline[$index];
                $gapMinutes = (int) floor(($current->starts_at->timestamp - $previous->ends_at->timestamp) / 60);
                if ($gapMinutes < $minimumRestMinutes) {
                    throw ValidationException::withMessages([
                        'attendance_roster' => 'An employee has less than the governed minimum rest of '.$minimumRestMinutes.' minutes between roster shifts.',
                    ]);
                }
            }
        }
    }

    /** @param Collection<int, AttendanceRosterEntry> $entries */
    private function assertMaximumConsecutiveWorkdays(AttendanceRoster $roster, Collection $entries, int $maximum): void
    {
        if ($maximum <= 0) {
            return;
        }

        foreach ($entries->pluck('employee_id')->unique() as $employeeId) {
            $authoritativeDates = AttendanceRosterEntry::query()
                ->where('company_id', $roster->company_id)
                ->where('employee_id', $employeeId)
                ->where('attendance_roster_id', '!=', $roster->id)
                ->where('entry_type', 'shift')
                ->whereBetween('work_date', [
                    $roster->period_start->copy()->subDays($maximum)->toDateString(),
                    $roster->period_end->copy()->addDays($maximum)->toDateString(),
                ])
                ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
                ->pluck('work_date');
            $dates = $entries
                ->where('employee_id', $employeeId)
                ->where('entry_type', 'shift')
                ->pluck('work_date')
                ->concat($authoritativeDates)
                ->map(fn ($date): string => Carbon::parse($date)->toDateString())
                ->unique()
                ->sort()
                ->values();
            $consecutive = 0;
            $previous = null;
            foreach ($dates as $date) {
                $consecutive = $previous !== null && Carbon::parse($previous)->addDay()->isSameDay(Carbon::parse($date))
                    ? $consecutive + 1
                    : 1;
                if ($consecutive > $maximum) {
                    throw ValidationException::withMessages([
                        'attendance_roster' => 'An employee exceeds the governed maximum of '.$maximum.' consecutive workdays.',
                    ]);
                }
                $previous = $date;
            }
        }
    }

    /** @param Collection<int, AttendanceRosterEntry> $entries */
    private function assertCompletePeriodAssignment(AttendanceRoster $roster, Collection $entries, AttendanceRosterRuleSet $rules): void
    {
        if (! $rules->requireCompletePeriodAssignment) {
            return;
        }

        $employees = $rules->coverageScope === 'all_active_employees'
            ? Employee::query()
                ->where('company_id', $roster->company_id)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('joined_on')->orWhereDate('joined_on', '<=', $roster->period_end))
                ->get(['id', 'joined_on'])
            : Employee::query()->whereIn('id', $entries->pluck('employee_id')->unique())->get(['id', 'joined_on']);
        $assigned = $entries->keyBy(fn (AttendanceRosterEntry $entry): string => $entry->employee_id.'|'.$entry->work_date->toDateString());

        foreach ($employees as $employee) {
            $date = $roster->period_start->copy();
            if ($employee->joined_on && $employee->joined_on->gt($date)) {
                $date = $employee->joined_on->copy();
            }
            while ($date->lte($roster->period_end)) {
                if (! $assigned->has($employee->id.'|'.$date->toDateString())) {
                    throw ValidationException::withMessages([
                        'attendance_roster' => 'Complete roster coverage is required. Employee #'.$employee->id.' has no assignment on '.$date->toDateString().'.',
                    ]);
                }
                $date->addDay();
            }
        }
    }

    private function assertSwapDecision(AttendanceShiftSwapRequest $swap, int $lockVersion, User $actor): void
    {
        $this->assertScopedCompany($actor, (int) $swap->company_id, 'attendance_shift_swap_request');
        $this->assertVersion($swap, $lockVersion, 'attendance_shift_swap_request');
        if ($swap->status !== 'submitted') {
            throw ValidationException::withMessages(['attendance_shift_swap_request' => 'Only submitted swap requests can be decided.']);
        }
        if ((int) $swap->requested_by_user_id === (int) $actor->id) {
            throw ValidationException::withMessages(['attendance_shift_swap_request' => 'The requester cannot approve or reject their own swap.']);
        }
    }

    private function assertNoSwapConflict(AttendanceRosterEntry $source, AttendanceRosterEntry $target, int $sourceNewEmployee, int $targetNewEmployee): void
    {
        $excludedEntryIds = [$source->id, $target->id];
        $sourceConflict = $this->authoritativeEntryConflictExists($sourceNewEmployee, $source, $excludedEntryIds);
        $targetConflict = $this->authoritativeEntryConflictExists($targetNewEmployee, $target, $excludedEntryIds);
        if ($sourceConflict || $targetConflict) {
            throw ValidationException::withMessages(['attendance_shift_swap_request' => 'The approved swap would create an overlapping roster assignment.']);
        }
    }

    private function occurrenceKeyFor(AttendanceRosterEntry $entry, int $employeeId, ?string $workDate = null): string
    {
        $date = Carbon::parse($workDate ?? $entry->work_date)->format('Ymd');
        if ($entry->attendance_rotation_rule_id !== null) {
            return sprintf(
                'rotation:%d:roster:%d:%s',
                $entry->attendance_rotation_rule_id,
                $entry->attendance_roster_id,
                $date,
            );
        }

        return sprintf('roster:%d:employee:%d:%s', $entry->attendance_roster_id, $employeeId, $date);
    }

    private function temporarySwapDate(AttendanceRosterEntry $entry): string
    {
        $candidate = Carbon::parse($entry->roster?->period_end ?? $entry->work_date)->addDay();
        while (AttendanceRosterEntry::query()
            ->where('attendance_roster_id', $entry->attendance_roster_id)
            ->where('employee_id', $entry->employee_id)
            ->whereDate('work_date', $candidate->toDateString())
            ->where('id', '!=', $entry->id)
            ->exists()) {
            $candidate->addDay();
        }

        return $candidate->toDateString();
    }

    /** @param array<int, int> $excludedEntryIds */
    private function authoritativeEntryConflictExists(int $employeeId, AttendanceRosterEntry $candidate, array $excludedEntryIds): bool
    {
        return AttendanceRosterEntry::query()
            ->where('company_id', $candidate->company_id)
            ->where('employee_id', $employeeId)
            ->whereNotIn('id', $excludedEntryIds)
            ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->where(function ($query) use ($candidate): void {
                $query->whereDate('work_date', $candidate->work_date);

                if ($candidate->starts_at && $candidate->ends_at) {
                    $query->orWhere(function ($overlap) use ($candidate): void {
                        $overlap->where('entry_type', 'shift')
                            ->whereNotNull('starts_at')
                            ->whereNotNull('ends_at')
                            ->where('starts_at', '<', $candidate->ends_at)
                            ->where('ends_at', '>', $candidate->starts_at);
                    });
                }
            })
            ->exists();
    }

    private function decideSwap(AttendanceShiftSwapRequest $swap, string $status, User $actor, ?string $note): void
    {
        $swap->forceFill([
            'status' => $status,
            'decided_by_user_id' => $actor->id,
            'decision_note' => $note,
            'decided_at' => now(),
            'lock_version' => $swap->lock_version + 1,
        ])->save();
    }

    private function createAttendanceSnapshot(AttendancePeriodLock $periodLock, Employee $employee, Carbon $start, Carbon $end): PayrollAttendanceSnapshot
    {
        $records = AttendanceRecord::query()
            ->where('company_id', $periodLock->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderBy('work_date')
            ->get();
        $entries = AttendanceRosterEntry::query()
            ->with('roster:id,rule_context')
            ->where('company_id', $periodLock->company_id)
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderBy('work_date')
            ->get();

        $presentDays = $records->whereIn('status', ['present', 'late', 'early_leave'])->count();
        $halfDays = $records->where('status', 'half_day')->count();
        $paidLeaveDays = $records->where('status', 'on_leave')->count();
        $paidCalendarDays = $records->whereIn('status', ['weekly_off', 'holiday'])->count();
        $unpaidDays = $records->where('status', 'absent')->count();
        $scheduledDays = $entries->where('entry_type', 'shift')->count();
        if ($scheduledDays === 0) {
            $scheduledDays = $records->count();
        }
        $payableHalfUnits = (($presentDays + $paidLeaveDays + $paidCalendarDays) * 2) + $halfDays;
        $attendanceRuleContexts = $records
            ->map(fn (AttendanceRecord $record): mixed => data_get($record->calculation_trace, 'rule_context'))
            ->filter(fn (mixed $context): bool => is_array($context))
            ->unique(fn (array $context): string => hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)))
            ->values()
            ->all();
        $rosterRuleContexts = $entries
            ->map(fn (AttendanceRosterEntry $entry): mixed => $entry->roster?->rule_context)
            ->filter(fn (mixed $context): bool => is_array($context))
            ->unique(fn (array $context): string => hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)))
            ->values()
            ->all();
        $sourceHash = hash('sha256', json_encode([
            'period_lock_source_hash' => $periodLock->source_hash,
            'employee_id' => $employee->id,
            'records' => $records->map(fn (AttendanceRecord $record) => [$record->id, $record->work_date->toDateString(), $record->status, $record->worked_minutes, $record->source_hash, $record->updated_at?->toISOString()])->all(),
            'entries' => $entries->map(fn (AttendanceRosterEntry $entry) => [$entry->id, $entry->work_date->toDateString(), $entry->entry_type, $entry->attendance_shift_id, $entry->lock_version, $entry->roster?->rule_context])->all(),
            'attendance_rule_contexts' => $attendanceRuleContexts,
            'roster_rule_contexts' => $rosterRuleContexts,
        ], JSON_THROW_ON_ERROR));

        return PayrollAttendanceSnapshot::create([
            'attendance_period_lock_id' => $periodLock->id,
            'company_id' => $periodLock->company_id,
            'employee_id' => $employee->id,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'scheduled_days' => $scheduledDays,
            'present_days' => $presentDays,
            'paid_leave_days' => $paidLeaveDays + $paidCalendarDays,
            'unpaid_days' => $unpaidDays,
            'worked_minutes' => (int) $records->sum('worked_minutes'),
            'payable_days' => $this->halfUnitsToDecimal($payableHalfUnits),
            'source_hash' => $sourceHash,
            'calculation_trace' => [
                'present_days' => $presentDays,
                'half_days' => $halfDays,
                'paid_leave_days' => $paidLeaveDays,
                'paid_weekly_off_or_holiday_days' => $paidCalendarDays,
                'unpaid_days' => $unpaidDays,
                'payable_half_units' => $payableHalfUnits,
                'rule' => 'present + paid leave + weekly off/holiday + (half day / 2)',
                'attendance_rule_contexts' => $attendanceRuleContexts,
                'roster_rule_contexts' => $rosterRuleContexts,
            ],
        ]);
    }

    private function periodSourceHash(int $companyId, Carbon $start, Carbon $end): string
    {
        $records = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderBy('id')
            ->get(['id', 'employee_id', 'work_date', 'status', 'worked_minutes', 'source_hash', 'updated_at']);
        $entries = AttendanceRosterEntry::query()
            ->where('company_id', $companyId)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderBy('id')
            ->with('roster:id,rule_context')
            ->get(['id', 'attendance_roster_id', 'employee_id', 'work_date', 'entry_type', 'attendance_shift_id', 'lock_version']);

        return hash('sha256', json_encode([
            'company_id' => $companyId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'records' => $records->toArray(),
            'entries' => $entries->map(fn (AttendanceRosterEntry $entry): array => [
                'id' => $entry->id,
                'employee_id' => $entry->employee_id,
                'work_date' => $entry->work_date->toDateString(),
                'entry_type' => $entry->entry_type,
                'attendance_shift_id' => $entry->attendance_shift_id,
                'lock_version' => $entry->lock_version,
                'rule_context' => $entry->roster?->rule_context,
            ])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function shiftInstants(string $workDate, AttendanceShift $shift, string $timezone): array
    {
        $start = Carbon::parse($workDate.' '.$shift->starts_at, $timezone);
        $end = Carbon::parse($workDate.' '.$shift->ends_at, $timezone);
        if ($shift->is_overnight || $end->lte($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    private function assertPublicationLead(AttendanceRoster $roster, AttendanceRosterRuleSet $rules): void
    {
        if ($rules->publicationLeadDays <= 0) {
            return;
        }

        $publishBy = Carbon::parse($roster->period_start->toDateString(), $rules->timezone)
            ->startOfDay()
            ->subDays($rules->publicationLeadDays)
            ->endOfDay();

        if (now($rules->timezone)->gt($publishBy)) {
            throw ValidationException::withMessages([
                'attendance_roster' => 'This roster must be published at least '.$rules->publicationLeadDays.' day(s) before the period starts.',
            ]);
        }
    }

    private function assertSwapCutoff(AttendanceRosterEntry $entry, string $field): void
    {
        $rules = $this->rulePacks->resolve((int) $entry->company_id, $entry->work_date);
        $cutoffHours = (int) (data_get($entry->roster?->rule_context, 'effective_values.swap_request_cutoff_hours') ?? $rules->swapRequestCutoffHours);
        if ($cutoffHours <= 0) {
            return;
        }

        if ($entry->starts_at === null) {
            throw ValidationException::withMessages([
                $field => 'The selected roster entry has no authoritative shift start time for cutoff validation.',
            ]);
        }

        if (now()->gte($entry->starts_at->copy()->subHours($cutoffHours))) {
            throw ValidationException::withMessages([
                $field => 'The governed shift-swap request cutoff of '.$cutoffHours.' hour(s) before shift start has passed.',
            ]);
        }
    }

    private function assertWithinReopenWindow(
        Carbon $periodEnd,
        int $limitDays,
        string $timezone,
        string $field,
        string $message,
    ): void {
        if ($limitDays <= 0) {
            return;
        }

        $deadline = Carbon::parse($periodEnd->toDateString(), $timezone)
            ->endOfDay()
            ->addDays($limitDays);
        if (now($timezone)->gt($deadline)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** @return array{0: int, 1: string} */
    private function pinnedReopenWindow(Model $record, string $limitKey, string $field): array
    {
        $context = $record->getAttribute('rule_context');
        $timezone = data_get($context, 'effective_values.timezone');
        $limitDays = data_get($context, 'effective_values.'.$limitKey);
        $checksum = data_get($context, 'packs.roster.checksum');

        if (
            ! is_array($context)
            || ! is_string($timezone)
            || ! in_array($timezone, timezone_identifiers_list(), true)
            || ! is_int($limitDays)
            || $limitDays < 0
            || ! is_string($checksum)
            || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => 'This historical record has no valid pinned governance context and cannot be reopened automatically. An authorized administrator must remediate it before retrying.',
            ]);
        }

        return [$limitDays, $timezone];
    }

    private function assertPinnedRotationContext(AttendanceRotationRule $rule, bool $matchingLegacy = false): void
    {
        $context = $rule->rule_context;
        $timezone = data_get($context, 'effective_values.timezone');
        $maximumHorizon = data_get($context, 'effective_values.maximum_rotation_generation_horizon_days');
        $checksum = data_get($context, 'packs.roster.checksum');

        if (
            ! is_array($context)
            || ! is_string($timezone)
            || ! in_array($timezone, timezone_identifiers_list(), true)
            || ! is_int($maximumHorizon)
            || $maximumHorizon < 1
            || $rule->generation_horizon_days > $maximumHorizon
            || ! is_string($checksum)
            || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
        ) {
            $prefix = $matchingLegacy ? 'The matching legacy rotation' : 'This legacy rotation';
            throw ValidationException::withMessages([
                'attendance_rotation_rule' => $prefix.' has no valid pinned governance context. Pause it and create a new governed rotation before generating authoritative entries.',
            ]);
        }
    }

    private function assertEmployeeInRoster(Employee $employee, AttendanceRoster $roster): void
    {
        if ((int) $employee->company_id !== (int) $roster->company_id || $employee->status !== 'active') {
            throw ValidationException::withMessages(['employee_id' => 'Select an active employee from the roster company.']);
        }
    }

    private function assertRosterDraft(AttendanceRoster $roster): void
    {
        if ($roster->status !== 'draft') {
            throw ValidationException::withMessages(['attendance_roster' => 'Only draft rosters can be edited or generated.']);
        }
    }

    private function assertScopedCompany(User $actor, int $companyId, string $field): void
    {
        if (! $this->companyScope->allows($actor, $companyId)) {
            throw ValidationException::withMessages([$field => 'The selected record is outside your company scope.']);
        }
    }

    private function assertVersion(object $model, int $version, string $field): void
    {
        if ((int) $model->lock_version !== $version) {
            throw ValidationException::withMessages([$field => 'This record changed in another session. Refresh before trying again.']);
        }
    }

    private function halfUnitsToDecimal(int $halfUnits): string
    {
        return intdiv($halfUnits, 2).($halfUnits % 2 === 0 ? '.00' : '.50');
    }

    private function nextSwapNumber(): string
    {
        do {
            $number = 'ASW-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (AttendanceShiftSwapRequest::query()->where('request_number', $number)->exists());

        return $number;
    }

    private function notifyRosterEmployees(AttendanceRoster $roster, User $actor, string $title, string $body, string $severity): void
    {
        $employeeIds = $roster->entries()->distinct()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
        $this->notifyEmployeeIds($employeeIds, $actor, $title, $body, $severity, $roster);
    }

    private function notifySwapParticipants(AttendanceShiftSwapRequest $swap, User $actor, string $title, string $body, string $severity): void
    {
        $swap->loadMissing(['requesterEmployee', 'sourceEntry', 'targetEntry']);
        $employeeIds = array_values(array_unique(array_filter([
            $swap->requester_employee_id,
            $swap->sourceEntry?->employee_id,
            $swap->targetEntry?->employee_id,
        ])));

        $this->notifyEmployeeIds($employeeIds, $actor, $title, $body, $severity, $swap);
    }

    /** @param array<int, int> $employeeIds */
    private function notifyEmployeeIds(array $employeeIds, User $actor, string $title, string $body, string $severity, Model $notifiable): void
    {
        $userIds = Employee::query()
            ->whereIn('id', $employeeIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->map(fn ($id): int => (int) $id)
            ->all();

        User::query()->whereIn('id', $userIds)->where('status', 'active')->each(function (User $recipient) use ($actor, $title, $body, $severity, $notifiable): void {
            $this->notifications->sendToUser($recipient, [
                'category' => 'hr',
                'severity' => $severity,
                'title' => $title,
                'body' => $body,
                'action_url' => '/hr/attendance-rosters',
            ], $actor, $notifiable);
        });
    }
}
