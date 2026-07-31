<?php

namespace App\Services\Recruitment;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Domain\Recruitment\Services\InterviewScheduleAvailability;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecruitmentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly InterviewScheduleAvailability $interviewAvailability,
        private readonly ActiveInternalUserEligibility $internalUsers,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createJobOpening(array $data, User $actor, ?Request $request = null): JobOpening
    {
        return DB::transaction(function () use ($data, $actor, $request): JobOpening {
            $this->assertCompanyScope($actor, $data['company_id'], 'company_id');

            $opening = JobOpening::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'created_by_user_id' => $actor->id,
                'opening_code' => $this->nextJobOpeningCode(),
                'title' => $data['title'],
                'department' => $data['department'],
                'designation' => $data['designation'],
                'positions' => $data['positions'],
                'employment_type' => $data['employment_type'],
                'work_location' => $data['work_location'] ?? null,
                'budget_min_ctc' => $data['budget_min_ctc'] ?? null,
                'budget_max_ctc' => $data['budget_max_ctc'] ?? null,
                'status' => 'pending_approval',
                'target_hiring_date' => $data['target_hiring_date'] ?? null,
                'required_skills' => $data['required_skills'] ?? [],
                'metadata' => [
                    'business_justification' => $data['business_justification'] ?? null,
                    'workflow_history' => [
                        $this->openingWorkflowEvent('pending_approval', $actor, 'Job requisition submitted for approval'),
                    ],
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'recruitment.job_opening.created',
                'Submitted job requisition',
                $opening,
                [
                    'opening_code' => $opening->opening_code,
                    'department' => $opening->department,
                    'positions' => $opening->positions,
                    'status' => $opening->status,
                ],
                $request,
            );

            return $opening->load($this->jobOpeningRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveJobOpening(JobOpening $jobOpening, array $data, User $actor, ?Request $request = null): JobOpening
    {
        return $this->reviewJobOpening($jobOpening, 'open', 'Approved job requisition', $data, $actor, $request);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function rejectJobOpening(JobOpening $jobOpening, array $data, User $actor, ?Request $request = null): JobOpening
    {
        return $this->reviewJobOpening($jobOpening, 'rejected', 'Rejected job requisition', $data, $actor, $request);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createCandidate(array $data, User $actor, ?Request $request = null): Candidate
    {
        return DB::transaction(function () use ($data, $actor, $request): Candidate {
            $opening = JobOpening::query()->whereKey($data['job_opening_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $opening->company_id, 'job_opening_id');

            if ($opening->status !== 'open') {
                throw ValidationException::withMessages(['job_opening_id' => 'The selected job opening is not open for your company.']);
            }

            $candidate = Candidate::create([
                'company_id' => $opening->company_id,
                'job_opening_id' => $opening->id,
                'owner_user_id' => $actor->id,
                'candidate_code' => $this->nextCandidateCode(),
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'source' => $data['source'],
                'current_company' => $data['current_company'] ?? null,
                'experience_years' => $data['experience_years'],
                'current_ctc' => $data['current_ctc'] ?? null,
                'expected_ctc' => $data['expected_ctc'] ?? null,
                'notice_period_days' => $data['notice_period_days'] ?? null,
                'skills' => $data['skills'] ?? [],
                'documents' => $data['documents'] ?? [],
                'stage' => 'screening',
                'status' => 'active',
                'stage_history' => [
                    $this->stageEvent('screening', $actor, 'Candidate created'),
                ],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->auditLogger->record(
                $actor,
                'recruitment.candidate.created',
                'Created recruitment candidate',
                $candidate,
                [
                    'candidate_code' => $candidate->candidate_code,
                    'source' => $candidate->source,
                    'job_opening_id' => $candidate->job_opening_id,
                ],
                $request,
            );

            return $candidate->load($this->candidateRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateCandidateStage(Candidate $candidate, array $data, User $actor, ?Request $request = null): Candidate
    {
        return DB::transaction(function () use ($candidate, $data, $actor, $request): Candidate {
            $candidate = Candidate::query()
                ->with(['offer'])
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $candidate->company_id, 'candidate');

            if ($candidate->status !== 'active') {
                throw ValidationException::withMessages(['candidate' => 'Only active candidates can be moved through the recruitment pipeline.']);
            }

            if ($candidate->employee_id !== null) {
                throw ValidationException::withMessages(['candidate' => 'Converted candidates cannot be moved through the recruitment pipeline.']);
            }

            $targetStage = (string) $data['stage'];
            $currentStage = (string) $candidate->stage;

            if ($targetStage === $currentStage) {
                throw ValidationException::withMessages(['stage' => 'The candidate is already in the requested stage.']);
            }

            $allowedTransitions = [
                'screening' => ['selected', 'rejected'],
                'interviewed' => ['selected', 'rejected'],
                'interview_scheduled' => ['rejected'],
                'selected' => ['rejected'],
            ];

            if (! in_array($targetStage, $allowedTransitions[$currentStage] ?? [], true)) {
                throw ValidationException::withMessages(['stage' => 'This candidate stage transition is not allowed. Use interview, offer or conversion workflows for controlled stages.']);
            }

            if (
                $targetStage === 'rejected'
                && $candidate->offer
                && in_array($candidate->offer->status, ['draft', 'released', 'accepted'], true)
            ) {
                throw ValidationException::withMessages(['stage' => 'Candidates with active offers cannot be rejected from the pipeline stage action.']);
            }

            $history = $candidate->stage_history ?? [];
            $history[] = $this->stageEvent(
                $targetStage,
                $actor,
                $data['transition_note'] ?? ($targetStage === 'selected' ? 'Candidate selected from pipeline' : 'Candidate rejected from pipeline'),
            );

            $candidate->forceFill([
                'stage' => $targetStage,
                'status' => $targetStage === 'rejected' ? 'rejected' : 'active',
                'stage_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'recruitment.candidate.stage_updated',
                'Updated recruitment candidate stage',
                $candidate,
                [
                    'candidate_code' => $candidate->candidate_code,
                    'from_stage' => $currentStage,
                    'to_stage' => $targetStage,
                    'transition_note' => $data['transition_note'] ?? null,
                ],
                $request,
            );

            return $candidate->load($this->candidateRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function scheduleInterview(array $data, User $actor, ?Request $request = null): Interview
    {
        return DB::transaction(function () use ($data, $actor, $request): Interview {
            $candidate = Candidate::query()
                ->whereKey($data['candidate_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertActiveCandidateForActor($candidate, $actor);
            $panelIds = collect($data['panel_user_ids'])->map(fn ($id): int => (int) $id)->unique()->values();
            $this->internalUsers->assertIdsEligible(
                $actor,
                $panelIds->all(),
                $candidate->company_id,
                'panel_user_ids',
                'Every interview panel member must be an active internal user in the candidate company.',
            );
            $this->interviewAvailability->assertAvailable(
                $candidate,
                $panelIds->all(),
                CarbonImmutable::parse((string) $data['scheduled_at']),
                (int) $data['duration_minutes'],
            );

            $interview = Interview::create([
                'company_id' => $candidate->company_id,
                'candidate_id' => $candidate->id,
                'scheduled_by_user_id' => $actor->id,
                'interview_code' => $this->nextInterviewCode(),
                'round_name' => $data['round_name'],
                'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'],
                'mode' => $data['mode'],
                'venue_or_link' => $data['venue_or_link'] ?? null,
                'panel_user_ids' => $panelIds->all(),
                'status' => 'scheduled',
                'feedback' => [],
            ]);

            $this->moveCandidateStage($candidate, 'interview_scheduled', $actor, 'Interview scheduled');

            $this->auditLogger->record(
                $actor,
                'recruitment.interview.scheduled',
                'Scheduled candidate interview',
                $interview,
                [
                    'interview_code' => $interview->interview_code,
                    'candidate_code' => $candidate->candidate_code,
                    'scheduled_at' => $interview->scheduled_at?->toISOString(),
                ],
                $request,
            );

            return $interview->load(['candidate.jobOpening', 'scheduledBy']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitInterviewFeedback(Interview $interview, array $data, User $actor, ?Request $request = null): Interview
    {
        return DB::transaction(function () use ($interview, $data, $actor, $request): Interview {
            $interview = Interview::query()
                ->with(['candidate.jobOpening', 'scheduledBy'])
                ->whereKey($interview->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $interview->company_id, 'interview');

            $this->internalUsers->assertEligible(
                $actor,
                $actor,
                $interview->company_id,
                'interview',
                'Only active internal panel members can submit interview feedback.',
            );

            $panelIds = collect($interview->panel_user_ids ?? [])->map(fn ($id): int => (int) $id)->unique()->values();

            if (! $panelIds->contains((int) $actor->id)) {
                throw ValidationException::withMessages(['interview' => 'Only assigned panel members can submit interview feedback.']);
            }

            if (! in_array($interview->status, ['scheduled', 'rescheduled', 'completed'], true)) {
                throw ValidationException::withMessages(['interview' => 'Feedback can be submitted only for scheduled interviews.']);
            }

            $feedback = $interview->feedback ?? [];
            $entries = collect($feedback['entries'] ?? []);

            if ($entries->contains(fn ($entry): bool => (int) ($entry['user_id'] ?? 0) === (int) $actor->id)) {
                throw ValidationException::withMessages(['interview' => 'This panel member has already submitted feedback for the interview.']);
            }

            $entries->push([
                'user_id' => $actor->id,
                'reviewer_name' => $actor->name,
                'reviewer_email' => $actor->email,
                'rating' => (int) $data['rating'],
                'recommendation' => $data['recommendation'],
                'strengths' => $data['strengths'] ?? null,
                'concerns' => $data['concerns'] ?? null,
                'feedback_note' => $data['feedback_note'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'panel_weight' => (float) ($data['panel_weight'] ?? 1),
                'competency_scores' => $data['competency_scores'] ?? [],
                'submitted_at' => now()->toISOString(),
            ]);

            $submittedPanelCount = $entries
                ->pluck('user_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->intersect($panelIds)
                ->count();

            $allPanelFeedbackSubmitted = $panelIds->isNotEmpty() && $submittedPanelCount >= $panelIds->count();
            $averageRating = round($entries->avg('rating'), 2);
            $recommendations = $entries
                ->countBy(fn ($entry): string => (string) ($entry['recommendation'] ?? 'unknown'))
                ->all();
            $competencyKeys = ['role_competency', 'technical_capability', 'communication', 'culture_fit', 'problem_solving'];
            $scoringInputs = collect($competencyKeys)->mapWithKeys(function (string $key) use ($entries): array {
                $rated = $entries->filter(fn (array $entry): bool => isset($entry['competency_scores'][$key]) && is_numeric($entry['competency_scores'][$key]));
                $weightTotal = (float) $rated->sum(fn (array $entry): float => (float) ($entry['panel_weight'] ?? 1));
                if ($weightTotal <= 0) {
                    return [];
                }
                $weighted = (float) $rated->sum(fn (array $entry): float => (float) $entry['competency_scores'][$key] * (float) ($entry['panel_weight'] ?? 1));
                return [$key => round($weighted / $weightTotal, 4)];
            })->all();

            $interview->forceFill([
                'status' => $allPanelFeedbackSubmitted ? 'completed' : $interview->status,
                'feedback' => [
                    'entries' => $entries->values()->all(),
                    'summary' => [
                        'average_rating' => $averageRating,
                        'recommendations' => $recommendations,
                        'submitted_count' => $submittedPanelCount,
                        'panel_count' => $panelIds->count(),
                        'completed' => $allPanelFeedbackSubmitted,
                        'last_submitted_at' => now()->toISOString(),
                    ],
                ],
                'scoring_inputs' => $scoringInputs,
            ])->save();

            $candidate = $interview->candidate()->lockForUpdate()->firstOrFail();

            if ($allPanelFeedbackSubmitted && in_array($candidate->stage, ['screening', 'interview_scheduled'], true)) {
                $this->moveCandidateStage($candidate, 'interviewed', $actor, 'Interview feedback completed');
            }

            $this->auditLogger->record(
                $actor,
                'recruitment.interview.feedback_submitted',
                'Submitted candidate interview feedback',
                $interview,
                [
                    'interview_code' => $interview->interview_code,
                    'candidate_code' => $candidate->candidate_code,
                    'rating' => (int) $data['rating'],
                    'recommendation' => $data['recommendation'],
                    'completed' => $allPanelFeedbackSubmitted,
                ],
                $request,
            );

            return $interview->load(['candidate.jobOpening', 'scheduledBy']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createOffer(array $data, User $actor, ?Request $request = null): JobOffer
    {
        return DB::transaction(function () use ($data, $actor, $request): JobOffer {
            $candidate = Candidate::query()
                ->whereKey($data['candidate_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertActiveCandidateForActor($candidate, $actor);

            if (! in_array($candidate->stage, ['interview_scheduled', 'interviewed', 'selected', 'offer_draft'], true)) {
                throw ValidationException::withMessages(['candidate_id' => 'An offer can be created only after interview scheduling or selection.']);
            }

            if (JobOffer::query()->where('company_id', $candidate->company_id)->where('candidate_id', $candidate->id)->whereIn('status', ['draft', 'released', 'accepted'])->exists()) {
                throw ValidationException::withMessages(['candidate_id' => 'An active offer already exists for this candidate.']);
            }

            $offer = JobOffer::create([
                'company_id' => $candidate->company_id,
                'candidate_id' => $candidate->id,
                'created_by_user_id' => $actor->id,
                'offer_number' => $this->nextOfferNumber(),
                'template_code' => $data['template_code'],
                'offered_ctc' => $data['offered_ctc'],
                'joining_date' => $data['joining_date'],
                'placeholders' => $data['placeholders'],
                'status' => 'draft',
                'document_history' => [
                    [
                        'event' => 'offer_draft_created',
                        'template_code' => $data['template_code'],
                        'actor' => $actor->name,
                        'at' => now()->toISOString(),
                    ],
                ],
            ]);

            $this->moveCandidateStage($candidate, 'offer_draft', $actor, 'Offer draft created');

            $this->auditLogger->record(
                $actor,
                'recruitment.offer.created',
                'Created candidate offer draft',
                $offer,
                [
                    'offer_number' => $offer->offer_number,
                    'candidate_code' => $candidate->candidate_code,
                    'offered_ctc' => $offer->offered_ctc,
                ],
                $request,
            );

            return $offer->load($this->offerRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function releaseOffer(JobOffer $jobOffer, array $data, User $actor, ?Request $request = null): JobOffer
    {
        return DB::transaction(function () use ($jobOffer, $data, $actor, $request): JobOffer {
            $offer = JobOffer::query()->whereKey($jobOffer->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $offer->company_id, 'offer');

            if ($offer->status !== 'draft') {
                throw ValidationException::withMessages(['offer' => 'Only draft offers can be released.']);
            }

            if ($offer->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['offer' => 'The offer creator cannot release the same offer.']);
            }

            $documentHistory = $offer->document_history ?? [];
            $documentHistory[] = [
                'event' => 'offer_released',
                'actor' => $actor->name,
                'note' => $data['release_note'] ?? null,
                'at' => now()->toISOString(),
            ];

            $offer->forceFill([
                'status' => 'released',
                'released_by_user_id' => $actor->id,
                'released_at' => now(),
                'document_history' => $documentHistory,
            ])->save();

            $candidate = $offer->candidate()->lockForUpdate()->firstOrFail();
            $this->moveCandidateStage($candidate, 'offer_released', $actor, 'Offer released');

            $this->auditLogger->record(
                $actor,
                'recruitment.offer.released',
                'Released candidate offer',
                $offer,
                [
                    'offer_number' => $offer->offer_number,
                    'candidate_code' => $candidate->candidate_code,
                    'release_note' => $data['release_note'] ?? null,
                ],
                $request,
            );

            return $offer->load($this->offerRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function convertCandidateToEmployee(Candidate $candidate, array $data, User $actor, ?Request $request = null): Candidate
    {
        return DB::transaction(function () use ($candidate, $data, $actor, $request): Candidate {
            $candidate = Candidate::query()
                ->with(['jobOpening'])
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $candidate->company_id, 'candidate');

            if ($candidate->status !== 'active') {
                throw ValidationException::withMessages(['candidate' => 'Only active candidates can be converted to employees.']);
            }

            if ($candidate->employee_id !== null) {
                throw ValidationException::withMessages(['candidate' => 'This candidate is already linked to an employee record.']);
            }

            $offer = JobOffer::query()
                ->where('candidate_id', $candidate->id)
                ->lockForUpdate()
                ->first();

            if (! $offer || $offer->status !== 'released') {
                throw ValidationException::withMessages(['candidate' => 'Candidate conversion requires a released offer.']);
            }

            if (($data['user_id'] ?? null) !== null) {
                $linkedUser = User::query()->with('role')->find((int) $data['user_id']);

                if (! $linkedUser) {
                    throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
                }

                $this->internalUsers->assertEligible(
                    $actor,
                    $linkedUser,
                    $candidate->company_id,
                    'user_id',
                    'The linked user must be an active internal user in the candidate company.',
                );

                if (Employee::query()->where('user_id', $linkedUser->id)->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'The selected user is already linked to an employee profile.']);
                }
            }

            $opening = $candidate->jobOpening;
            $employeeCode = $data['employee_code'] ?? $this->nextEmployeeCode();
            $joinedOn = $data['joined_on'] ?? $offer->joining_date?->toDateString();
            $monthlyCtc = $data['monthly_ctc'] ?? round(((float) $offer->offered_ctc) / 12, 2);
            $designation = $data['designation'] ?? $opening?->designation ?? ($offer->placeholders['designation'] ?? 'Employee');
            $department = $data['department'] ?? $opening?->department ?? ($offer->placeholders['department'] ?? 'General');
            $employmentType = $data['employment_type'] ?? $opening?->employment_type ?? 'full_time';
            $sensitiveProfile = array_merge(
                [
                    'source' => 'recruitment_conversion',
                    'candidate_code' => $candidate->candidate_code,
                    'candidate_email' => $candidate->email,
                    'candidate_phone' => $candidate->phone,
                    'candidate_source' => $candidate->source,
                    'offer_number' => $offer->offer_number,
                    'annual_offered_ctc' => (string) $offer->offered_ctc,
                ],
                $data['sensitive_profile'] ?? [],
            );

            $employee = Employee::create([
                'company_id' => $candidate->company_id,
                'branch_id' => $data['branch_id'] ?? $opening?->branch_id,
                'project_id' => $data['project_id'] ?? $opening?->project_id,
                'user_id' => $data['user_id'] ?? null,
                'manager_employee_id' => $data['manager_employee_id'] ?? null,
                'employee_code' => $employeeCode,
                'name' => $candidate->name,
                'designation' => $designation,
                'department' => $department,
                'grade' => $data['grade'] ?? null,
                'employment_type' => $employmentType,
                'status' => $data['status'] ?? 'active',
                'joined_on' => $joinedOn,
                'statutory_state' => $data['statutory_state'] ?? null,
                'monthly_ctc' => $monthlyCtc,
                'sensitive_profile' => $sensitiveProfile,
            ]);

            $offerHistory = $offer->document_history ?? [];
            $offerHistory[] = [
                'event' => 'offer_accepted_for_employee_conversion',
                'actor' => $actor->name,
                'note' => $data['acceptance_note'] ?? null,
                'employee_code' => $employee->employee_code,
                'at' => now()->toISOString(),
            ];

            $offer->forceFill([
                'status' => 'accepted',
                'accepted_by_user_id' => $actor->id,
                'accepted_at' => now(),
                'document_history' => $offerHistory,
            ])->save();

            $history = $candidate->stage_history ?? [];
            $history[] = $this->stageEvent('employee_created', $actor, 'Candidate converted to employee '.$employee->employee_code);

            $candidate->forceFill([
                'employee_id' => $employee->id,
                'stage' => 'employee_created',
                'status' => 'converted',
                'stage_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'recruitment.candidate.converted_to_employee',
                'Converted candidate to employee',
                $candidate,
                [
                    'candidate_code' => $candidate->candidate_code,
                    'employee_code' => $employee->employee_code,
                    'offer_number' => $offer->offer_number,
                    'joining_date' => $employee->joined_on?->toDateString(),
                    'monthly_ctc' => (string) $employee->monthly_ctc,
                ],
                $request,
            );

            return $candidate->load($this->candidateRelations());
        });
    }

    private function moveCandidateStage(Candidate $candidate, string $stage, User $actor, string $note): void
    {
        $history = $candidate->stage_history ?? [];
        $history[] = $this->stageEvent($stage, $actor, $note);

        $candidate->forceFill([
            'stage' => $stage,
            'stage_history' => $history,
        ])->save();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function reviewJobOpening(
        JobOpening $jobOpening,
        string $status,
        string $auditAction,
        array $data,
        User $actor,
        ?Request $request = null,
    ): JobOpening {
        return DB::transaction(function () use ($jobOpening, $status, $auditAction, $data, $actor, $request): JobOpening {
            $opening = JobOpening::query()->whereKey($jobOpening->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $opening->company_id, 'job_opening');

            if ($opening->status !== 'pending_approval') {
                throw ValidationException::withMessages(['job_opening' => 'Only pending requisitions can be reviewed.']);
            }

            if ($opening->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['job_opening' => 'The requisition creator cannot review the same requisition.']);
            }

            $metadata = $opening->metadata ?? [];
            $history = $metadata['workflow_history'] ?? [];
            $history[] = $this->openingWorkflowEvent($status, $actor, $data['review_note'] ?? $auditAction);
            $metadata['workflow_history'] = $history;

            $opening->forceFill([
                'status' => $status,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'metadata' => $metadata,
            ])->save();

            $this->auditLogger->record(
                $actor,
                $status === 'open' ? 'recruitment.job_opening.approved' : 'recruitment.job_opening.rejected',
                $auditAction,
                $opening,
                [
                    'opening_code' => $opening->opening_code,
                    'status' => $opening->status,
                    'review_note' => $data['review_note'] ?? null,
                ],
                $request,
            );

            return $opening->load($this->jobOpeningRelations());
        });
    }

    private function assertActiveCandidateForActor(Candidate $candidate, User $actor): void
    {
        $this->assertCompanyScope($actor, $candidate->company_id, 'candidate_id');

        if ($candidate->status !== 'active') {
            throw ValidationException::withMessages(['candidate_id' => 'The selected candidate is not active for your company.']);
        }
    }

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stageEvent(string $stage, User $actor, string $note): array
    {
        return [
            'stage' => $stage,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextCandidateCode(): string
    {
        return sprintf('CAN-%04d', Candidate::query()->withTrashed()->count() + 1001);
    }

    private function nextJobOpeningCode(): string
    {
        $nextNumber = JobOpening::query()->withTrashed()->count() + 1001;

        do {
            $code = sprintf('JOB-%04d', $nextNumber);
            $nextNumber++;
        } while (JobOpening::query()->withTrashed()->where('opening_code', $code)->exists());

        return $code;
    }

    private function nextInterviewCode(): string
    {
        return sprintf('INT-%04d', Interview::query()->withTrashed()->count() + 1001);
    }

    private function nextOfferNumber(): string
    {
        return sprintf('OFF-%04d', JobOffer::query()->withTrashed()->count() + 1001);
    }

    private function nextEmployeeCode(): string
    {
        $nextNumber = Employee::query()->withTrashed()->count() + 1001;

        do {
            $code = sprintf('EMP-%04d', $nextNumber);
            $nextNumber++;
        } while (Employee::query()->withTrashed()->where('employee_code', $code)->exists());

        return $code;
    }

    /**
     * @return array<int, string>
     */
    private function candidateRelations(): array
    {
        return ['jobOpening', 'owner', 'employee', 'interviews', 'offer.createdBy', 'offer.releasedBy', 'offer.acceptedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function jobOpeningRelations(): array
    {
        return ['company', 'branch', 'project', 'createdBy', 'reviewedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function offerRelations(): array
    {
        return ['candidate.jobOpening', 'createdBy', 'releasedBy', 'acceptedBy'];
    }

    /**
     * @return array<string, string|null>
     */
    private function openingWorkflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }
}
