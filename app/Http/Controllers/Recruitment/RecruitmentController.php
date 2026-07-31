<?php

namespace App\Http\Controllers\Recruitment;

use App\Application\Recruitment\Actions\ApproveJobOpening;
use App\Application\Recruitment\Actions\ChangeCandidateStage;
use App\Application\Recruitment\Actions\ConvertCandidateToEmployee;
use App\Application\Recruitment\Actions\CreateCandidate;
use App\Application\Recruitment\Actions\CreateJobOffer;
use App\Application\Recruitment\Actions\CreateJobOpening;
use App\Application\Recruitment\Actions\ListCandidates;
use App\Application\Recruitment\Actions\ListInterviews;
use App\Application\Recruitment\Actions\ListJobOffers;
use App\Application\Recruitment\Actions\ListJobOpenings;
use App\Application\Recruitment\Actions\ListRecruitmentWorkspace;
use App\Application\Recruitment\Actions\RejectJobOpening;
use App\Application\Recruitment\Actions\ReleaseJobOffer;
use App\Application\Recruitment\Actions\ScheduleCandidateInterview;
use App\Application\Recruitment\Actions\SubmitInterviewPanelFeedback;
use App\Application\Recruitment\Actions\ViewRecruitmentSourceSummary;
use App\Application\Recruitment\Data\InterviewFeedbackData;
use App\Application\Recruitment\Data\RecruitmentCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\CandidateIndexRequest;
use App\Http\Requests\Recruitment\ConvertCandidateToEmployeeRequest;
use App\Http\Requests\Recruitment\InterviewIndexRequest;
use App\Http\Requests\Recruitment\JobOfferIndexRequest;
use App\Http\Requests\Recruitment\JobOpeningIndexRequest;
use App\Http\Requests\Recruitment\RecruitmentSourceSummaryRequest;
use App\Http\Requests\Recruitment\ReleaseJobOfferRequest;
use App\Http\Requests\Recruitment\ReviewJobOpeningRequest;
use App\Http\Requests\Recruitment\ScheduleInterviewRequest;
use App\Http\Requests\Recruitment\StoreCandidateRequest;
use App\Http\Requests\Recruitment\StoreJobOfferRequest;
use App\Http\Requests\Recruitment\StoreJobOpeningRequest;
use App\Http\Requests\Recruitment\SubmitInterviewFeedbackRequest;
use App\Http\Requests\Recruitment\UpdateCandidateStageRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\InterviewResource;
use App\Http\Resources\JobOfferResource;
use App\Http\Resources\JobOpeningResource;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Services\Recruitment\InterviewPanelHydrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class RecruitmentController extends Controller
{
    public function sourceSummary(
        RecruitmentSourceSummaryRequest $request,
        ViewRecruitmentSourceSummary $view,
    ): JsonResponse {
        $summary = $view->execute($request->user(), $request->validated(), $request);

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function openings(JobOpeningIndexRequest $request, ListJobOpenings $list, ListRecruitmentWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $openings = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('recruitment.workspace.index', $workspace->execute($request->user(), $validated, 'openings', openings: $openings)->toView());
        }

        return JobOpeningResource::collection($openings);
    }

    public function storeOpening(StoreJobOpeningRequest $request, CreateJobOpening $create): JobOpeningResource|RedirectResponse
    {
        $opening = $create->execute(new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.job-openings.index', ['status' => 'pending_approval'])
                ->with('status', "Job requisition {$opening->opening_code} submitted for approval.");
        }

        return (new JobOpeningResource($opening))->additional(['message' => 'Job requisition submitted for approval.']);
    }

    public function approveOpening(
        JobOpening $jobOpening,
        ReviewJobOpeningRequest $request,
        ApproveJobOpening $approve,
    ): JobOpeningResource|RedirectResponse {
        $opening = $approve->execute($jobOpening, new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.job-openings.index', ['status' => 'open'])
                ->with('status', "Job requisition {$opening->opening_code} approved and opened.");
        }

        return (new JobOpeningResource($opening))->additional(['message' => 'Job requisition approved.']);
    }

    public function rejectOpening(
        JobOpening $jobOpening,
        ReviewJobOpeningRequest $request,
        RejectJobOpening $reject,
    ): JobOpeningResource|RedirectResponse {
        $opening = $reject->execute($jobOpening, new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.job-openings.index', ['status' => 'rejected'])
                ->with('status', "Job requisition {$opening->opening_code} rejected.");
        }

        return (new JobOpeningResource($opening))->additional(['message' => 'Job requisition rejected.']);
    }

    public function candidates(CandidateIndexRequest $request, ListCandidates $list, ListRecruitmentWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $candidates = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('recruitment.workspace.index', $workspace->execute($request->user(), $validated, 'candidates', candidates: $candidates)->toView());
        }

        return CandidateResource::collection($candidates);
    }

    public function pipeline(CandidateIndexRequest $request, ListCandidates $list, ListRecruitmentWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        if (! $request->wantsJson()) {
            return view('recruitment.workspace.index', $workspace->execute($request->user(), $validated, 'pipeline')->toView());
        }

        $candidates = $list->execute($request->user(), $validated);

        return CandidateResource::collection($candidates);
    }

    public function storeCandidate(StoreCandidateRequest $request, CreateCandidate $create): CandidateResource|RedirectResponse
    {
        $candidate = $create->execute(new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.candidates.index', ['stage' => $candidate->stage])
                ->with('status', "Candidate {$candidate->candidate_code} created.");
        }

        return (new CandidateResource($candidate))->additional(['message' => 'Candidate created.']);
    }

    public function updateCandidateStage(
        Candidate $candidate,
        UpdateCandidateStageRequest $request,
        ChangeCandidateStage $change,
    ): CandidateResource|RedirectResponse {
        $candidate = $change->execute($candidate, new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            $route = ($request->validated('return_to') === 'pipeline')
                ? 'recruitment.pipeline.index'
                : 'recruitment.candidates.index';

            return redirect()
                ->route($route, $route === 'recruitment.candidates.index' ? ['stage' => $candidate->stage] : [])
                ->with('status', "Candidate {$candidate->candidate_code} moved to {$candidate->stage}.");
        }

        return (new CandidateResource($candidate))->additional(['message' => 'Candidate stage updated.']);
    }

    public function interviews(InterviewIndexRequest $request, ListInterviews $list, ListRecruitmentWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $interviews = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('recruitment.workspace.index', $workspace->execute($request->user(), $validated, 'interviews', interviews: $interviews)->toView());
        }

        return InterviewResource::collection($interviews);
    }

    public function scheduleInterview(
        ScheduleInterviewRequest $request,
        ScheduleCandidateInterview $schedule,
        InterviewPanelHydrator $panelHydrator,
    ): InterviewResource|RedirectResponse {
        $interview = $schedule->execute(new RecruitmentCommandData($request->validated(), $request->user(), $request));
        $panelHydrator->hydrate([$interview]);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.interviews.index', ['status' => 'scheduled'])
                ->with('status', "Interview {$interview->interview_code} scheduled.");
        }

        return (new InterviewResource($interview))->additional(['message' => 'Interview scheduled.']);
    }

    public function submitInterviewFeedback(
        Interview $interview,
        SubmitInterviewFeedbackRequest $request,
        SubmitInterviewPanelFeedback $action,
        InterviewPanelHydrator $panelHydrator,
    ): InterviewResource|RedirectResponse {
        $interview = $action->execute(
            $interview,
            InterviewFeedbackData::from($request->validated()),
            $request->user(),
            $request,
        );
        $panelHydrator->hydrate([$interview]);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.interviews.index', ['status' => $interview->status])
                ->with('status', "Feedback submitted for interview {$interview->interview_code}.");
        }

        return (new InterviewResource($interview))->additional(['message' => 'Interview feedback submitted.']);
    }

    public function offers(JobOfferIndexRequest $request, ListJobOffers $list, ListRecruitmentWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $offers = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('recruitment.workspace.index', $workspace->execute($request->user(), $validated, 'offers', offers: $offers)->toView());
        }

        return JobOfferResource::collection($offers);
    }

    public function storeOffer(StoreJobOfferRequest $request, CreateJobOffer $create): JobOfferResource|RedirectResponse
    {
        $offer = $create->execute(new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.offers.index', ['status' => 'draft'])
                ->with('status', "Offer {$offer->offer_number} drafted.");
        }

        return (new JobOfferResource($offer))->additional(['message' => 'Offer draft created.']);
    }

    public function releaseOffer(JobOffer $jobOffer, ReleaseJobOfferRequest $request, ReleaseJobOffer $release): JobOfferResource|RedirectResponse
    {
        $offer = $release->execute($jobOffer, new RecruitmentCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('recruitment.offers.index', ['status' => 'released'])
                ->with('status', "Offer {$offer->offer_number} released.");
        }

        return (new JobOfferResource($offer))->additional(['message' => 'Offer released.']);
    }

    public function convertCandidateToEmployee(
        Candidate $candidate,
        ConvertCandidateToEmployeeRequest $request,
        ConvertCandidateToEmployee $convert,
    ): CandidateResource {
        $candidate = $convert->execute($candidate, new RecruitmentCommandData($request->validated(), $request->user(), $request));

        return (new CandidateResource($candidate))->additional(['message' => 'Candidate converted to employee.']);
    }

    private function openingStatuses(): array
    {
        return [
            'pending_approval' => 'Pending approval',
            'open' => 'Open',
            'on_hold' => 'On hold',
            'closed' => 'Closed',
            'rejected' => 'Rejected',
        ];
    }

    private function candidateStages(): array
    {
        return [
            'screening' => 'Screening',
            'interview_scheduled' => 'Interview scheduled',
            'interviewed' => 'Interviewed',
            'selected' => 'Selected',
            'offer_draft' => 'Offer draft',
            'offer_released' => 'Offer released',
            'employee_created' => 'Employee created',
            'rejected' => 'Rejected',
        ];
    }

    private function interviewStatuses(): array
    {
        return [
            'scheduled' => 'Scheduled',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }

    private function offerStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'released' => 'Released',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
        ];
    }

    private function employmentTypes(): array
    {
        return [
            'full_time' => 'Full time',
            'part_time' => 'Part time',
            'contract' => 'Contract',
            'intern' => 'Intern',
            'consultant' => 'Consultant',
        ];
    }
}
