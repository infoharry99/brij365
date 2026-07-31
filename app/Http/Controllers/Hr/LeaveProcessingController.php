<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveLeaveEncashment;
use App\Application\Hr\Actions\CreateLeaveProcessingRun;
use App\Application\Hr\Actions\ListLeaveEncashments;
use App\Application\Hr\Actions\ListLeaveProcessingRuns;
use App\Application\Hr\Actions\ListLeaveWorkspace;
use App\Application\Hr\Actions\MarkLeaveEncashmentForPayroll;
use App\Application\Hr\Actions\PostLeaveProcessingRun;
use App\Application\Hr\Actions\RejectLeaveEncashment;
use App\Application\Hr\Actions\SubmitLeaveEncashment;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveLeaveEncashmentRequest;
use App\Http\Requests\Hr\LeaveEncashmentIndexRequest;
use App\Http\Requests\Hr\LeaveProcessingRunIndexRequest;
use App\Http\Requests\Hr\MarkLeaveEncashmentPayrollRequest;
use App\Http\Requests\Hr\PostLeaveProcessingRunRequest;
use App\Http\Requests\Hr\RejectLeaveEncashmentRequest;
use App\Http\Requests\Hr\StoreLeaveEncashmentRequest;
use App\Http\Requests\Hr\StoreLeaveProcessingRunRequest;
use App\Http\Resources\LeaveEncashmentResource;
use App\Http\Resources\LeaveProcessingRunResource;
use App\Models\LeaveEncashment;
use App\Models\LeaveProcessingRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class LeaveProcessingController extends Controller
{
    public function processingRuns(LeaveProcessingRunIndexRequest $request, ListLeaveProcessingRuns $list, ListLeaveWorkspace $workspace): AnonymousResourceCollection|View
    {
        $actor = $request->user();
        $filters = $request->validated();

        $runs = $list->execute($actor, $filters);

        if (! $request->wantsJson()) {
            return view('hr.leave.workspace', $workspace->execute($actor, 'processing', runs: $runs)->toView());
        }

        return LeaveProcessingRunResource::collection($runs);
    }

    public function storeProcessingRun(StoreLeaveProcessingRunRequest $request, CreateLeaveProcessingRun $create): JsonResponse|RedirectResponse
    {
        $run = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-processing-runs.index')
                ->with('status', 'Leave processing preview '.$run->run_number.' generated.');
        }

        return (new LeaveProcessingRunResource($run))
            ->additional(['message' => 'Leave processing preview generated.'])
            ->response()
            ->setStatusCode(201);
    }

    public function postProcessingRun(LeaveProcessingRun $leaveProcessingRun, PostLeaveProcessingRunRequest $request, PostLeaveProcessingRun $post): LeaveProcessingRunResource|RedirectResponse
    {
        $posted = $post->execute($leaveProcessingRun, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-processing-runs.index')
                ->with('status', 'Leave processing run '.$posted->run_number.' posted.');
        }

        return (new LeaveProcessingRunResource($posted))->additional(['message' => 'Leave processing run posted.']);
    }

    public function encashments(LeaveEncashmentIndexRequest $request, ListLeaveEncashments $list, ListLeaveWorkspace $workspace): AnonymousResourceCollection|View
    {
        $actor = $request->user();
        $filters = $request->validated();

        $encashments = $list->execute($actor, $filters);

        if (! $request->wantsJson()) {
            return view('hr.leave.workspace', $workspace->execute($actor, 'encashments', encashments: $encashments)->toView());
        }

        return LeaveEncashmentResource::collection($encashments);
    }

    public function storeEncashment(StoreLeaveEncashmentRequest $request, SubmitLeaveEncashment $submit): JsonResponse|RedirectResponse
    {
        $encashment = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-encashments.index')
                ->with('status', 'Leave encashment '.$encashment->encashment_number.' submitted.');
        }

        return (new LeaveEncashmentResource($encashment))
            ->additional(['message' => 'Leave encashment request submitted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function approveEncashment(LeaveEncashment $leaveEncashment, ApproveLeaveEncashmentRequest $request, ApproveLeaveEncashment $approve): LeaveEncashmentResource|RedirectResponse
    {
        $approved = $approve->execute($leaveEncashment, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-encashments.index')
                ->with('status', 'Leave encashment '.$approved->encashment_number.' approved.');
        }

        return (new LeaveEncashmentResource($approved))->additional(['message' => 'Leave encashment approved.']);
    }

    public function rejectEncashment(LeaveEncashment $leaveEncashment, RejectLeaveEncashmentRequest $request, RejectLeaveEncashment $reject): LeaveEncashmentResource|RedirectResponse
    {
        $rejected = $reject->execute($leaveEncashment, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-encashments.index')
                ->with('status', 'Leave encashment '.$rejected->encashment_number.' rejected.');
        }

        return (new LeaveEncashmentResource($rejected))->additional(['message' => 'Leave encashment rejected.']);
    }

    public function markEncashmentPayroll(LeaveEncashment $leaveEncashment, MarkLeaveEncashmentPayrollRequest $request, MarkLeaveEncashmentForPayroll $mark): LeaveEncashmentResource|RedirectResponse
    {
        $marked = $mark->execute($leaveEncashment, new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('hr.leave-encashments.index')
                ->with('status', 'Leave encashment '.$marked->encashment_number.' marked for payroll.');
        }

        return (new LeaveEncashmentResource($marked))->additional(['message' => 'Leave encashment marked for payroll inclusion.']);
    }
}
