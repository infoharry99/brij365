<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\AttendanceWorkspaceData;
use App\Domain\Hr\Services\AttendanceWorkspaceRegister;
use App\Models\AttendanceRegularizationRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListAttendanceWorkspace
{
    public function __construct(private readonly AttendanceWorkspaceRegister $register) {}

    public function execute(
        User $u,
        string $active,
        array $filters = [],
        ?LengthAwarePaginator $shifts = null,
        ?LengthAwarePaginator $records = null,
        ?LengthAwarePaginator $regularizations = null,
    ): AttendanceWorkspaceData
    {
        $shifts = $active === 'shifts'
            ? $this->register->presentShifts($shifts ?? $this->register->shifts($u, $filters, 'page'))
            : null;
        $recordSource = in_array($active, ['records', 'exceptions', 'trace'], true)
            ? ($records ?? $this->register->records($u, $filters, 'page'))
            : null;
        $records = in_array($active, ['records', 'exceptions'], true)
            ? $this->register->presentRecords($recordSource)
            : null;
        $calculationTraces = $active === 'trace'
            ? $this->register->presentCalculationTraces($recordSource)
            : null;
        $regularizations = $active === 'regularizations'
            ? $this->register->presentRegularizations($regularizations ?? $this->register->regularizations($u, $filters, 'page'), $u)
            : null;
        $assignments = $active === 'assignments'
            ? $this->register->presentAssignments($this->register->assignments($u, $filters, 'page'))
            : null;

        $shifts?->setPath(route('hr.attendance-shifts.index'));
        $records?->setPath(route('hr.attendance-records.index'));
        $calculationTraces?->setPath(route('hr.attendance-records.index'));
        $regularizations?->setPath(route('hr.attendance-regularizations.index'));
        $assignments?->setPath(route('hr.attendance-shifts.index'));

        $attendanceSurface = in_array($active, ['records', 'exceptions', 'trace'], true);

        return new AttendanceWorkspaceData(
            activeRegister: $active,
            shifts: $shifts,
            records: $records,
            calculationTraces: $calculationTraces,
            regularizations: $regularizations,
            assignments: $assignments,
            summary: $this->register->summary($u, $filters),
            siteAttendance: $attendanceSurface ? $this->register->siteAttendance($u, $filters) : collect(),
            companies: $active === 'shifts' ? $this->register->companies($u) : collect(),
            employees: in_array($active, ['records', 'exceptions', 'trace', 'regularizations'], true)
                ? $this->register->employees($u)
                : collect(),
            statusFilters: [['value' => 'present', 'label' => 'Present'], ['value' => 'late', 'label' => 'Late'], ['value' => 'early_leave', 'label' => 'Early leave'], ['value' => 'half_day', 'label' => 'Half day'], ['value' => 'absent', 'label' => 'Absent'], ['value' => 'on_leave', 'label' => 'On leave'], ['value' => 'weekly_off', 'label' => 'Weekly off'], ['value' => 'holiday', 'label' => 'Holiday']],
            regularizationStatuses: [['value' => 'submitted', 'label' => 'Submitted'], ['value' => 'approved', 'label' => 'Approved'], ['value' => 'rejected', 'label' => 'Rejected']],
            shiftTypes: [['value' => 'fixed', 'label' => 'Fixed'], ['value' => 'flexible', 'label' => 'Flexible'], ['value' => 'rotational', 'label' => 'Rotational'], ['value' => 'night', 'label' => 'Night'], ['value' => 'split', 'label' => 'Split']],
            abilities: ['canCreateShift' => $u->hasPermission('attendance.manage'), 'canCreateRegularization' => $u->can('create', AttendanceRegularizationRequest::class), 'canApproveRegularization' => $u->hasPermission('attendance.approve'), 'canManageAttendance' => $u->hasPermission('attendance.manage')],
        );
    }
}
