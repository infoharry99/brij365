<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveLeaveRequest;
use App\Application\Hr\Actions\ListLeaveBalances;
use App\Application\Hr\Actions\ListLeaveRequests;
use App\Application\Hr\Actions\ListLeaveTypes;
use App\Application\Hr\Actions\ListLeaveWorkspace;
use App\Application\Hr\Actions\RejectLeaveRequest;
use App\Application\Hr\Actions\SubmitLeaveRequest;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveLeaveRequestRequest;
use App\Http\Requests\Hr\LeaveBalanceIndexRequest;
use App\Http\Requests\Hr\LeaveRequestIndexRequest;
use App\Http\Requests\Hr\LeaveTypeIndexRequest;
use App\Http\Requests\Hr\RejectLeaveRequestRequest;
use App\Http\Requests\Hr\StoreLeaveRequest;
use App\Http\Resources\EmployeeLeaveBalanceResource;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function types(LeaveTypeIndexRequest $request, ListLeaveTypes $list, ListLeaveWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $types = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('hr.leave.workspace', $workspace->execute($request->user(), 'types', types: $types)->toView());
        }

        return LeaveTypeResource::collection($types);
    }

    public function balances(LeaveBalanceIndexRequest $request, ListLeaveBalances $list, ListLeaveWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $balances = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('hr.leave.workspace', $workspace->execute($request->user(), 'balances', balances: $balances)->toView());
        }

        return EmployeeLeaveBalanceResource::collection($balances);
    }

    public function index(LeaveRequestIndexRequest $request, ListLeaveRequests $list, ListLeaveWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $leaveRequests = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('hr.leave.workspace', $workspace->execute($request->user(), 'requests', requests: $leaveRequests)->toView());
        }

        return LeaveRequestResource::collection($leaveRequests);
    }

    public function store(StoreLeaveRequest $request, SubmitLeaveRequest $submit): JsonResponse|RedirectResponse
    {
        $leaveRequest = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-requests.index')
                ->with('status', 'Leave request '.$leaveRequest->request_number.' submitted.');
        }

        return (new LeaveRequestResource($leaveRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(ApproveLeaveRequestRequest $request, LeaveRequest $leaveRequest, ApproveLeaveRequest $approve): LeaveRequestResource|RedirectResponse
    {
        $approved = $approve->execute($leaveRequest, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-requests.index')
                ->with('status', 'Leave request '.$approved->request_number.' approved.');
        }

        return new LeaveRequestResource($approved);
    }

    public function reject(
        RejectLeaveRequestRequest $request,
        LeaveRequest $leaveRequest,
        RejectLeaveRequest $reject,
    ): LeaveRequestResource|RedirectResponse {
        $rejected = $reject->execute($leaveRequest, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-requests.index')
                ->with('status', 'Leave request '.$rejected->request_number.' rejected.');
        }

        return new LeaveRequestResource($rejected);
    }
}
