<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\AddAttendanceRosterEntry;
use App\Application\Hr\Actions\CancelAttendanceShiftSwap;
use App\Application\Hr\Actions\CreateAttendanceRoster;
use App\Application\Hr\Actions\CreateAttendanceRotationRule;
use App\Application\Hr\Actions\CreateShiftAssignment;
use App\Application\Hr\Actions\DecideAttendanceShiftSwap;
use App\Application\Hr\Actions\FinalizeAttendancePeriod;
use App\Application\Hr\Actions\GenerateAttendanceRotation;
use App\Application\Hr\Actions\ListAttendanceRosters;
use App\Application\Hr\Actions\ReopenAttendancePeriod;
use App\Application\Hr\Actions\SubmitAttendanceShiftSwap;
use App\Application\Hr\Actions\TransitionAttendanceRoster;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AttendanceRosterIndexRequest;
use App\Http\Requests\Hr\CancelAttendanceShiftSwapRequest;
use App\Http\Requests\Hr\DecideAttendanceShiftSwapRequest;
use App\Http\Requests\Hr\FinalizeAttendancePeriodRequest;
use App\Http\Requests\Hr\GenerateAttendanceRotationRequest;
use App\Http\Requests\Hr\ReopenAttendancePeriodRequest;
use App\Http\Requests\Hr\StoreAttendanceRosterEntryRequest;
use App\Http\Requests\Hr\StoreAttendanceRosterRequest;
use App\Http\Requests\Hr\StoreAttendanceRotationRuleRequest;
use App\Http\Requests\Hr\StoreAttendanceShiftSwapRequest;
use App\Http\Requests\Hr\StoreShiftAssignmentRequest;
use App\Http\Requests\Hr\TransitionAttendanceRosterRequest;
use App\Models\AttendancePeriodLock;
use App\Models\AttendanceRoster;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShiftSwapRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AttendanceRosterController extends Controller
{
    public function index(AttendanceRosterIndexRequest $request, ListAttendanceRosters $list): View
    {
        return view('hr.attendance.rosters', $list->execute($request->user(), $request->validated())->toView());
    }

    public function storeAssignment(StoreShiftAssignmentRequest $request, CreateShiftAssignment $create): RedirectResponse
    {
        $assignment = $create->execute($this->command($request));

        return $this->back('Effective shift assignment created for '.$assignment->employee->name.'.');
    }

    public function storeRoster(StoreAttendanceRosterRequest $request, CreateAttendanceRoster $create): RedirectResponse
    {
        $roster = $create->execute($this->command($request));

        return $this->back('Roster '.$roster->name.' created as a draft.');
    }

    public function storeEntry(
        StoreAttendanceRosterEntryRequest $request,
        AttendanceRoster $attendanceRoster,
        AddAttendanceRosterEntry $create,
    ): RedirectResponse {
        $entry = $create->execute($attendanceRoster, $this->command($request));

        return $this->back('Roster entry added for '.$entry->employee->name.'.');
    }

    public function publish(
        TransitionAttendanceRosterRequest $request,
        AttendanceRoster $attendanceRoster,
        TransitionAttendanceRoster $transition,
    ): RedirectResponse {
        $transition->execute($attendanceRoster, 'published', $this->command($request));

        return $this->back('Roster published. Its entries now govern attendance resolution.');
    }

    public function lock(
        TransitionAttendanceRosterRequest $request,
        AttendanceRoster $attendanceRoster,
        TransitionAttendanceRoster $transition,
    ): RedirectResponse {
        $transition->execute($attendanceRoster, 'locked', $this->command($request));

        return $this->back('Roster locked. Normal changes and shift swaps are no longer permitted.');
    }

    public function reopenRoster(
        TransitionAttendanceRosterRequest $request,
        AttendanceRoster $attendanceRoster,
        TransitionAttendanceRoster $transition,
    ): RedirectResponse {
        $transition->execute($attendanceRoster, 'reopened', $this->command($request));

        return $this->back('Roster reopened to published state for governed correction.');
    }

    public function cancelRoster(
        TransitionAttendanceRosterRequest $request,
        AttendanceRoster $attendanceRoster,
        TransitionAttendanceRoster $transition,
    ): RedirectResponse {
        $transition->execute($attendanceRoster, 'cancelled', $this->command($request));

        return $this->back('Roster cancelled.');
    }

    public function storeRotation(StoreAttendanceRotationRuleRequest $request, CreateAttendanceRotationRule $create): RedirectResponse
    {
        $rotation = $create->execute($this->command($request));

        return $this->back('Rotation rule '.$rotation->name.' created.');
    }

    public function generateRotation(
        GenerateAttendanceRotationRequest $request,
        AttendanceRotationRule $attendanceRotationRule,
        AttendanceRoster $attendanceRoster,
        GenerateAttendanceRotation $generate,
    ): RedirectResponse {
        $count = $generate->execute($attendanceRotationRule, $attendanceRoster, $this->command($request));

        return $this->back($count.' deterministic rotation '.str('entry')->plural($count).' generated.');
    }

    public function storeSwap(StoreAttendanceShiftSwapRequest $request, SubmitAttendanceShiftSwap $submit): RedirectResponse
    {
        $swap = $submit->execute($this->command($request));

        return $this->back('Shift swap '.$swap->request_number.' submitted for approval.');
    }

    public function approveSwap(
        DecideAttendanceShiftSwapRequest $request,
        AttendanceShiftSwapRequest $attendanceShiftSwapRequest,
        DecideAttendanceShiftSwap $decide,
    ): RedirectResponse {
        $decide->execute($attendanceShiftSwapRequest, 'approved', $this->command($request));

        return $this->back('Shift swap approved and both roster entries updated atomically.');
    }

    public function rejectSwap(
        DecideAttendanceShiftSwapRequest $request,
        AttendanceShiftSwapRequest $attendanceShiftSwapRequest,
        DecideAttendanceShiftSwap $decide,
    ): RedirectResponse {
        $decide->execute($attendanceShiftSwapRequest, 'rejected', $this->command($request));

        return $this->back('Shift swap rejected.');
    }

    public function cancelSwap(
        CancelAttendanceShiftSwapRequest $request,
        AttendanceShiftSwapRequest $attendanceShiftSwapRequest,
        CancelAttendanceShiftSwap $cancel,
    ): RedirectResponse {
        $cancel->execute($attendanceShiftSwapRequest, $this->command($request));

        return $this->back('Shift swap request cancelled.');
    }

    public function finalizePeriod(FinalizeAttendancePeriodRequest $request, FinalizeAttendancePeriod $finalize): RedirectResponse
    {
        $period = $finalize->execute($this->command($request));

        return $this->back('Attendance finalized and '.$period->snapshots()->count().' payroll snapshots frozen.');
    }

    public function reopenPeriod(
        ReopenAttendancePeriodRequest $request,
        AttendancePeriodLock $attendancePeriodLock,
        ReopenAttendancePeriod $reopen,
    ): RedirectResponse {
        $reopen->execute($attendancePeriodLock, $this->command($request));

        return $this->back('Attendance period reopened. Generated payroll runs for the period were marked stale.');
    }

    private function command(\Illuminate\Foundation\Http\FormRequest $request): HrCommandData
    {
        return new HrCommandData($request->validated(), $request->user(), $request);
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->back()->with('status', $message);
    }
}
