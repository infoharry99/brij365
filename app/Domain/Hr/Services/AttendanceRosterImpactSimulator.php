<?php

namespace App\Domain\Hr\Services;

use App\Application\Scoring\DTOs\RosterImpactSimulationInputData;
use App\Application\Scoring\DTOs\RosterImpactSimulationResultData;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final readonly class AttendanceRosterImpactSimulator
{
    public function __construct(
        private AttendanceRosterRulePackResolver $rulePacks,
        private CanonicalPayrollHasher $hasher,
    ) {
    }

    public function simulate(
        AttendanceRotationRule $rotation,
        RosterImpactSimulationInputData $input,
    ): RosterImpactSimulationResultData {
        $rotation->loadMissing('employee:id,company_id,employee_code,name,status');
        if ($rotation->status !== 'active' || ! $rotation->employee || $rotation->employee->status !== 'active') {
            throw ValidationException::withMessages([
                'attendance_rotation_rule_id' => 'Select an active rotation belonging to an active employee.',
            ]);
        }

        $start = Carbon::parse($input->startDate)->startOfDay();
        $end = Carbon::parse($input->endDate)->startOfDay();
        $ruleSet = $this->rulePacks->resolve((int) $rotation->company_id, $start);
        $maxDays = min(
            max(1, (int) $rotation->generation_horizon_days),
            max(1, $ruleSet->maximumRotationGenerationHorizonDays),
        );
        if ($start->diffInDays($end) + 1 > $maxDays) {
            throw ValidationException::withMessages([
                'end_date' => 'The simulation range may not exceed the governed generation horizon of '.$maxDays.' days.',
            ]);
        }

        $pattern = array_values((array) $rotation->pattern);
        if ((int) $rotation->cycle_days < 1 || count($pattern) !== (int) $rotation->cycle_days) {
            throw ValidationException::withMessages([
                'attendance_rotation_rule_id' => 'The selected rotation pattern is incomplete and cannot be simulated.',
            ]);
        }

        $shiftIds = collect($pattern)
            ->filter(static fn (mixed $item): bool => is_array($item) && ($item['type'] ?? null) === 'shift')
            ->pluck('attendance_shift_id')->filter()->map(static fn (mixed $id): int => (int) $id)->unique()->values();
        $shifts = AttendanceShift::withTrashed()
            ->where('company_id', $rotation->company_id)
            ->whereIn('id', $shiftIds)
            ->get()->keyBy('id');

        $authoritative = AttendanceRosterEntry::query()
            ->with(['roster:id,status', 'shift:id,code,name'])
            ->where('company_id', $rotation->company_id)
            ->where('employee_id', $rotation->employee_id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('roster', static fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderBy('work_date')
            ->orderByRaw("case source when 'swap' then 1 when 'override' then 2 when 'manual' then 3 when 'rotation' then 4 else 5 end")
            ->orderBy('id')
            ->get()->groupBy(static fn (AttendanceRosterEntry $entry): string => $entry->work_date->toDateString());

        $previousEnd = AttendanceRosterEntry::query()
            ->where('company_id', $rotation->company_id)
            ->where('employee_id', $rotation->employee_id)
            ->whereDate('work_date', '<', $start)
            ->where('entry_type', 'shift')
            ->whereNotNull('ends_at')
            ->whereHas('roster', static fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->value('ends_at');
        $previousEnd = $previousEnd ? Carbon::parse($previousEnd) : null;

        $days = [];
        $findings = [];
        $workdayStreak = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            $offset = (int) $rotation->anchor_date->diffInDays($current, false);
            $cycleIndex = (($offset % $rotation->cycle_days) + $rotation->cycle_days) % $rotation->cycle_days;
            $item = is_array($pattern[$cycleIndex] ?? null) ? $pattern[$cycleIndex] : [];
            $type = (string) ($item['type'] ?? 'missing');
            $shift = $type === 'shift' ? $shifts->get((int) ($item['attendance_shift_id'] ?? 0)) : null;
            $dayFindings = [];
            $startsAt = null;
            $endsAt = null;

            if ($type === 'shift') {
                $workdayStreak++;
                if (! $shift || ! $shift->is_active || $shift->trashed()) {
                    $dayFindings[] = $this->finding('missing_shift', $current, 'The rotation references a missing or inactive shift.', 'danger');
                } else {
                    [$startsAt, $endsAt] = $this->shiftInstants($current, $shift, $ruleSet->timezone);
                    if ($previousEnd && $ruleSet->minimumRestMinutes > 0) {
                        $restMinutes = (int) $previousEnd->diffInMinutes($startsAt, false);
                        if ($restMinutes < $ruleSet->minimumRestMinutes) {
                            $dayFindings[] = $this->finding(
                                'minimum_rest',
                                $current,
                                'Only '.$restMinutes.' minutes of rest precede this shift; '.$ruleSet->minimumRestMinutes.' minutes are required.',
                                'danger',
                            );
                        }
                    }
                    $previousEnd = $endsAt;
                }

                if ($ruleSet->maximumConsecutiveWorkdays > 0 && $workdayStreak > $ruleSet->maximumConsecutiveWorkdays) {
                    $dayFindings[] = $this->finding(
                        'maximum_consecutive_workdays',
                        $current,
                        'This is consecutive workday '.$workdayStreak.'; the governed maximum is '.$ruleSet->maximumConsecutiveWorkdays.'.',
                        'danger',
                    );
                }
            } elseif (in_array($type, ['off', 'holiday'], true)) {
                $workdayStreak = 0;
            } else {
                $workdayStreak = 0;
                $dayFindings[] = $this->finding('missing_pattern_day', $current, 'No valid rotation entry exists for this cycle day.', 'danger');
            }

            $existingEntries = $authoritative->get($current->toDateString(), collect());
            /** @var AttendanceRosterEntry|null $existing */
            $existing = $existingEntries->first();
            if ($existingEntries->count() > 1) {
                $dayFindings[] = $this->finding(
                    'authoritative_ambiguity',
                    $current,
                    $existingEntries->count().' published or locked roster entries exist for this employee and date; governed source precedence selected the effective comparison entry.',
                    'danger',
                );
            }
            if ($existing) {
                $matches = $existing->entry_type === $type
                    && (int) ($existing->attendance_shift_id ?? 0) === (int) ($shift?->id ?? 0);
                $dayFindings[] = $this->finding(
                    $matches ? 'authoritative_match' : 'authoritative_collision',
                    $current,
                    $matches
                        ? 'A published or locked roster already contains the same assignment.'
                        : 'A published or locked roster already contains a different assignment for this date.',
                    $matches ? 'info' : 'danger',
                );
            }

            $findings = [...$findings, ...$dayFindings];
            $days[] = [
                'date' => $current->toDateString(),
                'day_label' => $current->format('D, d M Y'),
                'cycle_index' => $cycleIndex + 1,
                'entry_type' => $type,
                'shift_id' => $shift?->id,
                'shift_code' => $shift?->code,
                'shift_name' => $shift?->name,
                'starts_at_local' => $startsAt?->copy()->setTimezone($ruleSet->timezone)->format('d M Y, h:i A'),
                'ends_at_local' => $endsAt?->copy()->setTimezone($ruleSet->timezone)->format('d M Y, h:i A'),
                'authoritative_entry_id' => $existing?->id,
                'authoritative_entry_count' => $existingEntries->count(),
                'finding_codes' => array_column($dayFindings, 'code'),
            ];
            $current->addDay();
        }

        $storedChecksum = (string) data_get($rotation->rule_context, 'packs.roster.checksum', '');
        $resolvedChecksum = (string) data_get($ruleSet->ruleContext, 'roster.checksum', '');
        if ($storedChecksum !== '' && $resolvedChecksum !== '' && ! hash_equals($storedChecksum, $resolvedChecksum)) {
            $findings[] = [
                'code' => 'rule_context_changed',
                'date' => $start->toDateString(),
                'message' => 'The active roster pack differs from the version pinned when this rotation was created.',
                'tone' => 'warning',
            ];
        }

        $counts = [
            'days' => count($days),
            'shift_days' => count(array_filter($days, static fn (array $day): bool => $day['entry_type'] === 'shift')),
            'off_days' => count(array_filter($days, static fn (array $day): bool => $day['entry_type'] === 'off')),
            'holidays' => count(array_filter($days, static fn (array $day): bool => $day['entry_type'] === 'holiday')),
            'blocking_findings' => count(array_filter($findings, static fn (array $finding): bool => $finding['tone'] === 'danger')),
            'warnings' => count(array_filter($findings, static fn (array $finding): bool => $finding['tone'] === 'warning')),
            'authoritative_matches' => count(array_filter($findings, static fn (array $finding): bool => $finding['code'] === 'authoritative_match')),
        ];
        $inputPayload = [
            'rotation_rule_id' => (int) $rotation->id,
            'rotation_lock_version' => (int) $rotation->lock_version,
            'rotation_rule_context' => $rotation->rule_context,
            'active_rule_context' => $ruleSet->ruleContext,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
        $inputHash = $this->hasher->hash($inputPayload);
        $resultHash = $this->hasher->hash([
            'input_hash' => $inputHash,
            'days' => $days,
            'findings' => $findings,
            'counts' => $counts,
        ]);

        return new RosterImpactSimulationResultData(
            rotationRuleId: (int) $rotation->id,
            rotationName: $rotation->name,
            employeeName: $rotation->employee->name,
            employeeCode: $rotation->employee->employee_code,
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            timezone: $ruleSet->timezone,
            days: $days,
            findings: $findings,
            counts: $counts,
            ruleContext: $ruleSet->ruleContext,
            inputHash: $inputHash,
            resultHash: $resultHash,
        );
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function shiftInstants(Carbon $workDate, AttendanceShift $shift, string $timezone): array
    {
        $startsAt = Carbon::parse($workDate->toDateString().' '.$shift->starts_at, $timezone);
        $endsAt = Carbon::parse($workDate->toDateString().' '.$shift->ends_at, $timezone);
        if ($shift->is_overnight || $endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }

        return [$startsAt->utc(), $endsAt->utc()];
    }

    /** @return array{code:string,date:string,message:string,tone:string} */
    private function finding(string $code, Carbon $date, string $message, string $tone): array
    {
        return [
            'code' => $code,
            'date' => $date->toDateString(),
            'message' => $message,
            'tone' => $tone,
        ];
    }
}
