<?php

namespace App\Domain\Recruitment\Services;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Application\Recruitment\Data\CandidateRowData;
use App\Application\Recruitment\Data\InterviewRowData;
use App\Application\Recruitment\Data\JobOfferRowData;
use App\Application\Recruitment\Data\JobOpeningRowData;
use App\Application\Recruitment\Data\RecruitmentPipelineStageData;
use App\Application\Recruitment\Data\RecruitmentSummaryData;
use App\Application\Scoring\Actions\ReadCurrentScores;
use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\Project;
use App\Models\User;
use App\Services\Recruitment\InterviewPanelHydrator;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class RecruitmentWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly InterviewPanelHydrator $panels,
        private readonly ReadCurrentScores $scores,
        private readonly ActiveInternalUserEligibility $internalUsers,
    ) {}

    public function openings(User $user, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        $q = JobOpening::query()->with(['company', 'branch', 'project', 'createdBy', 'reviewedBy']);
        $this->scope->apply($q, $user);

        return $q->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department', $v))->latest()->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $page);
    }

    public function candidates(User $user, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        $rows = $this->candidateQuery($user, $filters)
            ->latest()
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $page);
        $this->panels->hydrate($rows->getCollection()->pluck('interviews')->flatten(1));

        return $rows;
    }

    /**
     * Build each pipeline stage from its own bounded, scoped query.
     *
     * @param  array<int, string>  $stages
     * @return Collection<int, RecruitmentPipelineStageData>
     */
    public function pipelineStages(User $user, array $filters, array $stages, bool $showCompensation): Collection
    {
        $limit = $this->pagination->workspacePerPage($filters['per_page'] ?? null);
        $stageLabels = $this->candidateStageLabels();

        return collect($stages)->map(function (string $stage) use ($user, $filters, $showCompensation, $limit, $stageLabels): RecruitmentPipelineStageData {
            $query = $this->candidateQuery($user, $filters)->where('stage', $stage);
            $total = (clone $query)->count();
            $candidates = $query->latest()->limit($limit)->get();

            $this->panels->hydrate($candidates->pluck('interviews')->flatten(1));

            return new RecruitmentPipelineStageData(
                stage: $stage,
                total: $total,
                limit: $limit,
                candidates: $candidates
                    ->map(fn (Candidate $candidate): CandidateRowData => $this->presentCandidate($user, $candidate, $showCompensation, $stageLabels))
                    ->values(),
            );
        });
    }

    public function interviews(User $user, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        $q = Interview::query()->with(['candidate.jobOpening', 'scheduledBy']);
        $this->scope->apply($q, $user);
        $rows = $q->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('scheduled_at', $v))->orderBy('scheduled_at')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $page);
        $this->panels->hydrate($rows->getCollection());

        return $rows;
    }

    public function offers(User $user, array $filters = [], string $page = 'page'): LengthAwarePaginator
    {
        $q = JobOffer::query()->with(['candidate.jobOpening', 'createdBy', 'releasedBy', 'acceptedBy']);
        $this->scope->apply($q, $user);

        return $q->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest()->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $page);
    }

    public function summary(User $user): RecruitmentSummaryData
    {
        $openings = JobOpening::query();
        $this->scope->apply($openings, $user);

        $candidates = Candidate::query();
        $this->scope->apply($candidates, $user);

        $interviews = Interview::query();
        $this->scope->apply($interviews, $user);

        $offers = JobOffer::query();
        $this->scope->apply($offers, $user);

        $pipeline = (clone $candidates)
            ->selectRaw('stage, COUNT(*) as aggregate')
            ->groupBy('stage')
            ->pluck('aggregate', 'stage')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return new RecruitmentSummaryData(
            openRequisitions: (clone $openings)->where('status', 'open')->count(),
            openPositions: (int) (clone $openings)->where('status', 'open')->sum('positions'),
            activeCandidates: (clone $candidates)->where('status', 'active')->count(),
            scheduledInterviews: (clone $interviews)->where('status', 'scheduled')->count(),
            draftOffers: (clone $offers)->where('status', 'draft')->count(),
            convertedCandidates: (clone $candidates)->where('stage', 'employee_created')->count(),
            pipeline: $pipeline,
        );
    }

    public function presentOpenings(User $user, LengthAwarePaginator $rows, bool $showCompensation): LengthAwarePaginator
    {
        $rows->setCollection($rows->getCollection()->map(function (JobOpening $opening) use ($user, $showCompensation): JobOpeningRowData {
            $statusLabels = $this->openingStatusLabels();

            return new JobOpeningRowData(
                id: $opening->id,
                code: $opening->opening_code,
                title: $opening->title,
                designation: $opening->designation,
                department: $opening->department,
                positions: (int) $opening->positions,
                employmentType: $this->headline($opening->employment_type),
                location: $opening->work_location ?: 'Not specified',
                targetDate: $opening->target_hiring_date?->format('d M Y') ?? 'No target date',
                status: $opening->status,
                statusLabel: $statusLabels[$opening->status] ?? $this->headline($opening->status),
                statusTone: $this->statusTone($opening->status),
                createdBy: $opening->createdBy?->name ?? 'Unknown',
                reviewedBy: $opening->reviewedBy?->name ?? 'Review pending',
                budgetRange: $showCompensation ? $this->range($opening->budget_min_ctc, $opening->budget_max_ctc) : null,
                canApprove: $user->can('approve', $opening),
                canReject: $user->can('reject', $opening),
            );
        }));

        return $rows->withQueryString();
    }

    public function presentCandidates(User $user, LengthAwarePaginator $rows, bool $showCompensation): LengthAwarePaginator
    {
        $stageLabels = $this->candidateStageLabels();
        $rows->setCollection($rows->getCollection()->map(
            fn (Candidate $candidate): CandidateRowData => $this->presentCandidate($user, $candidate, $showCompensation, $stageLabels)
        ));

        return $rows->withQueryString();
    }

    /** @param array<string, string> $stageLabels */
    private function presentCandidate(User $user, Candidate $candidate, bool $showCompensation, array $stageLabels): CandidateRowData
    {
        $allowedStages = [];
        if ($user->can('update', $candidate) && $candidate->status === 'active' && $candidate->employee_id === null) {
            $allowed = [
                'screening' => ['selected', 'rejected'],
                'interviewed' => ['selected', 'rejected'],
                'interview_scheduled' => ['rejected'],
                'selected' => ['rejected'],
            ][$candidate->stage] ?? [];

            if ($candidate->offer && in_array($candidate->offer->status, ['draft', 'released', 'accepted'], true)) {
                $allowed = array_values(array_diff($allowed, ['rejected']));
            }

            foreach ($allowed as $stage) {
                $allowedStages[$stage] = $stageLabels[$stage] ?? $this->headline($stage);
            }
        }

        return new CandidateRowData(
            id: $candidate->id,
            code: $candidate->candidate_code,
            name: $candidate->name,
            initials: $this->initials($candidate->name),
            email: $candidate->email,
            phone: $candidate->phone,
            source: $candidate->source,
            currentCompany: $candidate->current_company ?: 'Not provided',
            experience: number_format((float) $candidate->experience_years, 1).' years',
            ctcSummary: $showCompensation ? $this->range($candidate->current_ctc, $candidate->expected_ctc, 'Current', 'Expected') : null,
            openingCode: $candidate->jobOpening?->opening_code ?? 'Opening unavailable',
            openingTitle: $candidate->jobOpening?->title ?? 'No opening title',
            department: $candidate->jobOpening?->department ?? 'No department',
            stage: $candidate->stage,
            stageLabel: $stageLabels[$candidate->stage] ?? $this->headline($candidate->stage),
            stageTone: $this->statusTone($candidate->stage),
            status: $candidate->status,
            owner: $candidate->owner?->name ?? 'Unassigned',
            interviewCount: $candidate->interviews->count(),
            offerStatus: $candidate->offer?->status ? $this->headline($candidate->offer->status) : 'No offer',
            allowedStages: $allowedStages,
            canConvert: $user->can('convert', $candidate) && $candidate->stage === 'offer_released' && $candidate->employee_id === null,
        );
    }

    private function candidateQuery(User $user, array $filters): Builder
    {
        $query = Candidate::query()->with([
            'jobOpening',
            'owner',
            'employee',
            'interviews',
            'offer.createdBy',
            'offer.releasedBy',
            'offer.acceptedBy',
        ]);
        $this->scope->apply($query, $user);

        return $query
            ->when($filters['stage'] ?? null, fn (Builder $query, string $stage) => $query->where('stage', $stage))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('candidate_code', 'like', "%{$search}%")
            ));
    }

    public function presentInterviews(User $user, LengthAwarePaginator $rows): LengthAwarePaginator
    {
        $scoreMap = $this->interviewScores($user, $rows);
        $statusLabels = $this->interviewStatusLabels();

        $rows->setCollection($rows->getCollection()->map(function (Interview $interview) use ($user, $scoreMap, $statusLabels): InterviewRowData {
            $summary = data_get($interview->feedback, 'summary', []);
            $score = $scoreMap[$interview->id] ?? null;

            return new InterviewRowData(
                id: $interview->id,
                code: $interview->interview_code,
                round: $interview->round_name,
                candidateCode: $interview->candidate?->candidate_code ?? 'Candidate unavailable',
                candidateName: $interview->candidate?->name ?? 'No candidate name',
                openingTitle: $interview->candidate?->jobOpening?->title ?? 'No opening title',
                scheduledDate: $interview->scheduled_at?->format('d M Y') ?? 'Not scheduled',
                scheduledTime: $interview->scheduled_at?->format('h:i A') ?? '',
                duration: number_format((int) $interview->duration_minutes).' min',
                mode: $this->headline($interview->mode),
                venue: $interview->venue_or_link ?: 'Not specified',
                panel: $interview->getRelation('panelUsers')->pluck('name')->values()->all(),
                status: $interview->status,
                statusLabel: $statusLabels[$interview->status] ?? $this->headline($interview->status),
                statusTone: $this->statusTone($interview->status),
                submittedFeedback: (int) ($summary['submitted_count'] ?? 0),
                expectedFeedback: (int) ($summary['panel_count'] ?? count($interview->panel_user_ids ?? [])),
                averageRating: isset($summary['average_rating']) ? number_format((float) $summary['average_rating'], 1).' / 5' : null,
                score: $score?->score,
                scoreBand: $score?->band ? $this->headline($score->band) : null,
                scoreRule: $score ? 'Rule v'.$score->ruleVersion : null,
                canSubmitFeedback: $user->can('submitFeedback', $interview)
                    && ! collect(data_get($interview->feedback, 'entries', []))->contains(fn ($entry): bool => (int) ($entry['user_id'] ?? 0) === (int) $user->id),
            );
        }));

        return $rows->withQueryString();
    }

    public function presentOffers(User $user, LengthAwarePaginator $rows, bool $showCompensation): LengthAwarePaginator
    {
        $statusLabels = $this->offerStatusLabels();
        $rows->setCollection($rows->getCollection()->map(function (JobOffer $offer) use ($user, $showCompensation, $statusLabels): JobOfferRowData {
            return new JobOfferRowData(
                id: $offer->id,
                number: $offer->offer_number,
                candidateCode: $offer->candidate?->candidate_code ?? 'Candidate unavailable',
                candidateName: $offer->candidate?->name ?? 'No candidate name',
                openingTitle: $offer->candidate?->jobOpening?->title ?? 'No opening title',
                department: $offer->candidate?->jobOpening?->department ?? 'No department',
                template: $offer->template_code,
                offeredCtc: $showCompensation ? $this->money($offer->offered_ctc) : null,
                joiningDate: $offer->joining_date?->format('d M Y') ?? 'Not specified',
                status: $offer->status,
                statusLabel: $statusLabels[$offer->status] ?? $this->headline($offer->status),
                statusTone: $this->statusTone($offer->status),
                createdBy: $offer->createdBy?->name ?? 'Unknown',
                releasedBy: $offer->releasedBy?->name ?? 'Release pending',
                releasedAt: $offer->released_at?->format('d M Y, h:i A') ?? 'Not released',
                canRelease: $user->can('release', $offer),
            );
        }));

        return $rows->withQueryString();
    }

    public function scoped(User $u, string $class): mixed
    {
        $q = $class::query();
        $this->scope->apply($q, $u);

        return $q;
    }

    public function companies(User $u): Collection
    {
        $q = Company::query();
        $this->scope->apply($q, $u, 'id');

        return $q->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name']);
    }

    public function branches(User $u): Collection
    {
        return $this->scoped($u, Branch::class)->orderBy('code')->get(['id', 'company_id', 'code', 'name']);
    }

    public function projects(User $u): Collection
    {
        return $this->scoped($u, Project::class)->where('status', 'active')->orderBy('code')->get(['id', 'company_id', 'code', 'name']);
    }

    public function openingOptions(User $u): Collection
    {
        return $this->scoped($u, JobOpening::class)->where('status', 'open')->orderBy('opening_code')->get(['id', 'company_id', 'opening_code', 'title', 'department', 'designation']);
    }

    public function candidateOptions(User $u, bool $offers = false): Collection
    {
        $q = $this->scoped($u, Candidate::class)->where('status', 'active')->with('jobOpening');
        if ($offers) {
            $q->whereIn('stage', ['interview_scheduled', 'interviewed', 'selected', 'offer_draft']);
        }

        return $q->orderBy('candidate_code')->get(['id', 'company_id', 'candidate_code', 'name', 'stage', 'job_opening_id']);
    }

    public function panelUsers(User $u): Collection
    {
        return $this->internalUsers->forActor($u);
    }

    public function departments(User $u): Collection
    {
        return $this->scoped($u, JobOpening::class)->select('department')->whereNotNull('department')->distinct()->orderBy('department')->pluck('department')->filter()->values();
    }

    public function sources(User $u): Collection
    {
        return collect(['Referral', 'LinkedIn', 'Naukri', 'Walk-in', 'Consultant', 'Employee referral'])->merge($this->scoped($u, Candidate::class)->select('source')->whereNotNull('source')->distinct()->pluck('source'))->filter()->unique()->values();
    }

    public function interviewScores(User $u, LengthAwarePaginator $rows): array
    {
        $id = $this->scope->companyIdFor($u);

        return $id === null ? [] : $this->scores->execute($id, 'recruitment_interview', Interview::class, $rows->getCollection()->modelKeys());
    }

    /** @return array<string, string> */
    private function openingStatusLabels(): array
    {
        return ['pending_approval' => 'Pending approval', 'open' => 'Open', 'on_hold' => 'On hold', 'closed' => 'Closed', 'rejected' => 'Rejected'];
    }

    /** @return array<string, string> */
    private function candidateStageLabels(): array
    {
        return ['screening' => 'Screening', 'interview_scheduled' => 'Interview scheduled', 'interviewed' => 'Interviewed', 'selected' => 'Selected', 'offer_draft' => 'Offer draft', 'offer_released' => 'Offer released', 'employee_created' => 'Employee created', 'rejected' => 'Rejected'];
    }

    /** @return array<string, string> */
    private function interviewStatusLabels(): array
    {
        return ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    }

    /** @return array<string, string> */
    private function offerStatusLabels(): array
    {
        return ['draft' => 'Draft', 'released' => 'Released', 'accepted' => 'Accepted', 'rejected' => 'Rejected'];
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'open', 'completed', 'accepted', 'employee_created' => 'is-success',
            'pending_approval', 'scheduled', 'selected', 'offer_draft', 'offer_released', 'interview_scheduled', 'interviewed' => 'is-warning',
            'rejected', 'cancelled', 'closed' => 'is-danger',
            default => 'is-muted',
        };
    }

    private function headline(?string $value): string
    {
        return str((string) $value)->replace('_', ' ')->headline()->toString();
    }

    private function initials(string $name): string
    {
        return str($name)->squish()->explode(' ')->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'NA';
    }

    private function range(mixed $minimum, mixed $maximum, string $minimumLabel = 'Min', string $maximumLabel = 'Max'): ?string
    {
        if ($minimum === null && $maximum === null) {
            return null;
        }

        return $minimumLabel.' '.($minimum !== null ? $this->money($minimum) : 'NA').' / '.$maximumLabel.' '.($maximum !== null ? $this->money($maximum) : 'NA');
    }

    private function money(mixed $amount): string
    {
        return 'INR '.number_format((float) $amount, 2);
    }
}
