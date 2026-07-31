<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ListExitInterviews;
use App\Application\Hr\Actions\ListExitInterviewWorkspace;
use App\Application\Hr\Actions\ReviewExitInterview;
use App\Application\Hr\Actions\ScheduleExitInterview;
use App\Application\Hr\Actions\SubmitExitInterview;
use App\Application\Hr\Actions\ViewExitInterviewSummary;
use App\Application\Hr\Data\ExitInterviewSubmissionData;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ExitInterviewIndexRequest;
use App\Http\Requests\Hr\ExitInterviewSummaryRequest;
use App\Http\Requests\Hr\ReviewExitInterviewRequest;
use App\Http\Requests\Hr\StoreExitInterviewRequest;
use App\Http\Requests\Hr\SubmitExitInterviewRequest;
use App\Http\Resources\EmployeeExitInterviewResource;
use App\Models\EmployeeExitInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeExitInterviewController extends Controller
{
    public function index(ExitInterviewIndexRequest $request, ListExitInterviews $list, ListExitInterviewWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.exit-interviews.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return EmployeeExitInterviewResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function summary(ExitInterviewSummaryRequest $request, ViewExitInterviewSummary $summary, ListExitInterviewWorkspace $workspace): JsonResponse|View
    {
        if (! $request->wantsJson()) {
            return view('hr.exit-interviews.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return response()->json([
            'data' => $summary->execute($request->user(), $request->validated()),
        ]);
    }

    public function store(StoreExitInterviewRequest $request, ScheduleExitInterview $schedule): JsonResponse|RedirectResponse
    {
        $exitInterview = $schedule->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.exit-interviews.index')->with('status', 'Exit interview '.$exitInterview->interview_number.' scheduled.');
        }

        return (new EmployeeExitInterviewResource($exitInterview))
            ->additional(['message' => 'Employee exit interview scheduled.'])
            ->response()
            ->setStatusCode(201);
    }

    public function submit(
        EmployeeExitInterview $employeeExitInterview,
        SubmitExitInterviewRequest $request,
        SubmitExitInterview $action,
    ): EmployeeExitInterviewResource|RedirectResponse {
        $interview = $action->execute(
            $employeeExitInterview,
            ExitInterviewSubmissionData::from($request->validated()),
            $request->user(),
            $request,
        );

        return $request->wantsJson() ? (new EmployeeExitInterviewResource($interview))->additional(['message' => 'Employee exit interview submitted.']) : redirect()->route('hr.exit-interviews.index')->with('status', 'Exit interview submitted.');
    }

    public function review(EmployeeExitInterview $employeeExitInterview, ReviewExitInterviewRequest $request, ReviewExitInterview $review): EmployeeExitInterviewResource|RedirectResponse
    {
        $interview = $review->execute($employeeExitInterview, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new EmployeeExitInterviewResource($interview))->additional(['message' => 'Employee exit interview reviewed.']) : redirect()->route('hr.exit-interviews.index')->with('status', 'Exit interview reviewed.');
    }
}
