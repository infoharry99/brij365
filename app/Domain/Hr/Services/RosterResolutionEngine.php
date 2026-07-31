<?php

namespace App\Domain\Hr\Services;

use App\Models\AttendanceRosterEntry;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class RosterResolutionEngine
{
    /**
     * Resolve the authoritative schedule for an employee's local work date.
     * Published/locked dated entries always precede effective assignments.
     */
    public function resolve(Employee $employee, Carbon|string $workDate): AttendanceRosterEntry|EmployeeShiftAssignment|null
    {
        $date = Carbon::parse($workDate)->startOfDay();

        return $this->resolveRange(collect([$employee]), $date, $date)[$employee->id][$date->toDateString()] ?? null;
    }

    /**
     * Resolve schedules for a bounded employee/date range without issuing a
     * query for every employee-day. The returned map is keyed by employee id
     * and local work date.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, array<string, AttendanceRosterEntry|EmployeeShiftAssignment|null>>
     */
    public function resolveRange(Collection $employees, Carbon|string $start, Carbon|string $end): array
    {
        $from = Carbon::parse($start)->startOfDay();
        $to = Carbon::parse($end)->startOfDay();
        $employees = $employees
            ->filter(fn ($employee): bool => $employee instanceof Employee)
            ->unique('id')
            ->values();

        if ($employees->isEmpty() || $from->gt($to)) {
            return [];
        }

        $employeeIds = $employees->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $companyIds = $employees->pluck('company_id')->map(fn ($id): int => (int) $id)->unique()->all();
        $dateFrom = $from->toDateString();
        $dateTo = $to->toDateString();

        $datedEntries = AttendanceRosterEntry::query()
            ->with(['shift', 'roster'])
            ->whereIn('company_id', $companyIds)
            ->whereIn('employee_id', $employeeIds)
            ->whereDate('work_date', '>=', $dateFrom)
            ->whereDate('work_date', '<=', $dateTo)
            ->whereHas('roster', fn ($query) => $query->whereIn('status', ['published', 'locked']))
            ->orderBy('employee_id')
            ->orderBy('work_date')
            ->orderByRaw("case source when 'swap' then 1 when 'override' then 2 when 'manual' then 3 when 'rotation' then 4 else 5 end")
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AttendanceRosterEntry $entry): string => $entry->employee_id.'|'.$entry->work_date->toDateString());

        $assignments = EmployeeShiftAssignment::query()
            ->with('shift')
            ->whereIn('company_id', $companyIds)
            ->whereIn('employee_id', $employeeIds)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $dateTo)
            ->where(function ($query) use ($dateFrom): void {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $dateFrom);
            })
            ->whereHas('shift', fn ($query) => $query->where('is_active', true))
            ->orderBy('employee_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        $resolved = [];
        foreach ($employees as $employee) {
            $employeeAssignments = $assignments->get($employee->id, collect());

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $dateString = $date->toDateString();
                $entry = $datedEntries->get($employee->id.'|'.$dateString)?->first();
                if ($entry instanceof AttendanceRosterEntry) {
                    $resolved[(int) $employee->id][$dateString] = $entry;

                    continue;
                }

                $assignment = $employeeAssignments->first(
                    fn (EmployeeShiftAssignment $candidate): bool =>
                        $candidate->effective_from->lte($date)
                        && ($candidate->effective_to === null || $candidate->effective_to->gte($date)),
                );
                $resolved[(int) $employee->id][$dateString] = $assignment instanceof EmployeeShiftAssignment
                    ? $assignment
                    : null;
            }
        }

        return $resolved;
    }
}
