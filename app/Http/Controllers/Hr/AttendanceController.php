<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveAttendanceRegularization;
use App\Application\Hr\Actions\CreateAttendanceShift;
use App\Application\Hr\Actions\ListAttendanceRecords;
use App\Application\Hr\Actions\ListAttendanceRegularizations;
use App\Application\Hr\Actions\ListAttendanceShifts;
use App\Application\Hr\Actions\ListAttendanceWorkspace;
use App\Application\Hr\Actions\RejectAttendanceRegularization;
use App\Application\Hr\Actions\SubmitAttendanceRegularization;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveAttendanceRegularizationRequest;
use App\Http\Requests\Hr\AttendanceRecordIndexRequest;
use App\Http\Requests\Hr\AttendanceRegularizationIndexRequest;
use App\Http\Requests\Hr\AttendanceShiftIndexRequest;
use App\Http\Requests\Hr\RejectAttendanceRegularizationRequest;
use App\Http\Requests\Hr\StoreAttendanceRegularizationRequest;
use App\Http\Requests\Hr\StoreAttendanceShiftRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Http\Resources\AttendanceRegularizationResource;
use App\Http\Resources\AttendanceShiftResource;
use App\Models\AttendanceRegularizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function shifts(AttendanceShiftIndexRequest $request, ListAttendanceShifts $list, ListAttendanceWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $shifts = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            $active = ($validated['view'] ?? 'shifts') === 'assignments' ? 'assignments' : 'shifts';

            return view('hr.attendance.workspace', $workspace->execute($request->user(), $active, $validated, shifts: $shifts)->toView());
        }

        return AttendanceShiftResource::collection($shifts);
    }

    public function storeShift(StoreAttendanceShiftRequest $request, CreateAttendanceShift $create): JsonResponse|RedirectResponse
    {
        $shift = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.attendance-shifts.index')
                ->with('status', 'Attendance shift '.$shift->code.' created.');
        }

        return (new AttendanceShiftResource($shift))
            ->response()
            ->setStatusCode(201);
    }

    public function records(AttendanceRecordIndexRequest $request, ListAttendanceRecords $list, ListAttendanceWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $records = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            $active = match ($validated['view'] ?? 'records') {
                'exceptions' => 'exceptions',
                'trace' => 'trace',
                default => 'records',
            };

            return view('hr.attendance.workspace', $workspace->execute($request->user(), $active, $validated, records: $records)->toView());
        }

        return AttendanceRecordResource::collection($records);
    }

    public function regularizations(AttendanceRegularizationIndexRequest $request, ListAttendanceRegularizations $list, ListAttendanceWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $regularizations = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('hr.attendance.workspace', $workspace->execute($request->user(), 'regularizations', $validated, regularizations: $regularizations)->toView());
        }

        return AttendanceRegularizationResource::collection($regularizations);
    }

    public function storeRegularization(
        StoreAttendanceRegularizationRequest $request,
        SubmitAttendanceRegularization $submit,
    ): JsonResponse|RedirectResponse {
        $regularization = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.attendance-regularizations.index')
                ->with('status', 'Attendance regularization '.$regularization->request_number.' submitted.');
        }

        return (new AttendanceRegularizationResource($regularization))
            ->response()
            ->setStatusCode(201);
    }

    public function approveRegularization(
        ApproveAttendanceRegularizationRequest $request,
        AttendanceRegularizationRequest $regularization,
        ApproveAttendanceRegularization $approve,
    ): AttendanceRegularizationResource|RedirectResponse {
        $approved = $approve->execute($regularization, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.attendance-regularizations.index')
                ->with('status', 'Attendance regularization '.$approved->request_number.' approved.');
        }

        return new AttendanceRegularizationResource($approved);
    }

    public function rejectRegularization(
        RejectAttendanceRegularizationRequest $request,
        AttendanceRegularizationRequest $regularization,
        RejectAttendanceRegularization $reject,
    ): AttendanceRegularizationResource|RedirectResponse {
        $rejected = $reject->execute($regularization, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.attendance-regularizations.index')
                ->with('status', 'Attendance regularization '.$rejected->request_number.' rejected.');
        }

        return new AttendanceRegularizationResource($rejected);
    }
}
