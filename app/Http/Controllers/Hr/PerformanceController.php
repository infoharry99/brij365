<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ClosePerformanceReview;
use App\Application\Hr\Actions\CalibratePerformanceReview;
use App\Application\Hr\Actions\DecidePerformanceScoreOverride;
use App\Application\Hr\Actions\RequestPerformanceScoreOverride;
use App\Application\Hr\Actions\CreatePerformanceCycle;
use App\Application\Hr\Actions\CreatePerformanceReview;
use App\Application\Hr\Actions\ListPerformanceCycles;
use App\Application\Hr\Actions\ListPerformanceReviews;
use App\Application\Hr\Actions\ListPerformanceWorkspace;
use App\Application\Hr\Actions\SubmitManagerPerformanceReview;
use App\Application\Hr\Actions\SubmitSelfPerformanceReview;
use App\Application\Hr\Data\HrCommandData;
use App\Application\Hr\Data\ManagerPerformanceReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ClosePerformanceReviewRequest;
use App\Http\Requests\Hr\CalibratePerformanceReviewRequest;
use App\Http\Requests\Hr\DecidePerformanceScoreOverrideRequest;
use App\Http\Requests\Hr\PerformanceCycleIndexRequest;
use App\Http\Requests\Hr\PerformanceDashboardRequest;
use App\Http\Requests\Hr\PerformanceReviewIndexRequest;
use App\Http\Requests\Hr\StorePerformanceCycleRequest;
use App\Http\Requests\Hr\StorePerformanceReviewRequest;
use App\Http\Requests\Hr\StorePerformanceScoreOverrideRequest;
use App\Http\Requests\Hr\SubmitManagerPerformanceReviewRequest;
use App\Http\Requests\Hr\SubmitSelfPerformanceReviewRequest;
use App\Http\Resources\PerformanceCycleResource;
use App\Http\Resources\PerformanceReviewResource;
use App\Models\PerformanceReview;
use App\Models\PerformanceScoreOverrideRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerformanceController extends Controller
{
    public function dashboard(PerformanceDashboardRequest $request, ListPerformanceWorkspace $workspace): View
    {
        return view('hr.performance.workspace', $workspace->execute($request->user(), $request->validated(), 'dashboard')->toView());
    }

    public function cycles(PerformanceCycleIndexRequest $request, ListPerformanceCycles $list, ListPerformanceWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.performance.workspace', $workspace->execute($request->user(), $request->validated(), 'cycles')->toView());
        }

        return PerformanceCycleResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function storeCycle(StorePerformanceCycleRequest $request, CreatePerformanceCycle $create): PerformanceCycleResource|RedirectResponse
    {
        $cycle = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new PerformanceCycleResource($cycle))->additional(['message' => 'Performance cycle created.'])
            : redirect()->route('hr.performance-cycles.index')->with('status', 'Performance cycle '.$cycle->cycle_code.' created.');
    }

    public function reviews(PerformanceReviewIndexRequest $request, ListPerformanceReviews $list, ListPerformanceWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.performance.workspace', $workspace->execute($request->user(), $request->validated(), 'reviews')->toView());
        }

        return PerformanceReviewResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function storeReview(StorePerformanceReviewRequest $request, CreatePerformanceReview $create): PerformanceReviewResource|RedirectResponse
    {
        $review = $create->execute(new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new PerformanceReviewResource($review))->additional(['message' => 'Performance review created.'])
            : redirect()->route('hr.performance-reviews.index')->with('status', 'Performance review '.$review->review_number.' created.');
    }

    public function submitSelf(PerformanceReview $performanceReview, SubmitSelfPerformanceReviewRequest $request, SubmitSelfPerformanceReview $submit): PerformanceReviewResource|RedirectResponse
    {
        $review = $submit->execute($performanceReview, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new PerformanceReviewResource($review))->additional(['message' => 'Self-review submitted.']) : redirect()->route('hr.performance-reviews.index')->with('status', 'Self-review submitted.');
    }

    public function submitManager(
        PerformanceReview $performanceReview,
        SubmitManagerPerformanceReviewRequest $request,
        SubmitManagerPerformanceReview $action,
    ): PerformanceReviewResource|RedirectResponse {
        $review = $action->execute(
            $performanceReview,
            ManagerPerformanceReviewData::from($request->validated()),
            $request->user(),
            $request,
        );

        return $request->wantsJson() ? (new PerformanceReviewResource($review))->additional(['message' => 'Manager review submitted.']) : redirect()->route('hr.performance-reviews.index')->with('status', 'Manager review submitted.');
    }

    public function close(PerformanceReview $performanceReview, ClosePerformanceReviewRequest $request, ClosePerformanceReview $close): PerformanceReviewResource|RedirectResponse
    {
        $review = $close->execute($performanceReview, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new PerformanceReviewResource($review))->additional(['message' => 'Performance review closed.']) : redirect()->route('hr.performance-reviews.index')->with('status', 'Performance review closed.');
    }

    public function calibrate(PerformanceReview $performanceReview, CalibratePerformanceReviewRequest $request, CalibratePerformanceReview $action): PerformanceReviewResource|RedirectResponse
    {
        $review = $action->execute($performanceReview, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new PerformanceReviewResource($review))->additional(['message' => 'Governed performance score calculated.'])
            : redirect()->route('hr.performance-reviews.index')->with('status', 'Governed performance score calculated.');
    }

    public function requestOverride(PerformanceReview $performanceReview, StorePerformanceScoreOverrideRequest $request, RequestPerformanceScoreOverride $action): JsonResponse|RedirectResponse
    {
        $override = $action->execute($performanceReview, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? response()->json([
                'message' => 'Score override request submitted for separate approval.',
                'override_request' => [
                    'id' => $override->id,
                    'performance_review_id' => $override->performance_review_id,
                    'score_snapshot_id' => $override->score_snapshot_id,
                    'requested_score' => (float) $override->requested_score,
                    'status' => $override->status,
                    'review_lock_version' => (int) $override->review->lock_version,
                ],
            ], 201)
            : redirect()->route('hr.performance-reviews.index')->with('status', 'Score override request #'.$override->id.' submitted for separate approval.');
    }

    public function approveOverride(PerformanceScoreOverrideRequest $performanceScoreOverrideRequest, DecidePerformanceScoreOverrideRequest $request, DecidePerformanceScoreOverride $action): PerformanceReviewResource|RedirectResponse
    {
        $review = $action->execute($performanceScoreOverrideRequest, true, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new PerformanceReviewResource($review))->additional(['message' => 'Score override approved.'])
            : redirect()->route('hr.performance-reviews.index')->with('status', 'Score override approved.');
    }

    public function rejectOverride(PerformanceScoreOverrideRequest $performanceScoreOverrideRequest, DecidePerformanceScoreOverrideRequest $request, DecidePerformanceScoreOverride $action): PerformanceReviewResource|RedirectResponse
    {
        $review = $action->execute($performanceScoreOverrideRequest, false, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new PerformanceReviewResource($review))->additional(['message' => 'Score override rejected.'])
            : redirect()->route('hr.performance-reviews.index')->with('status', 'Score override rejected.');
    }
}
