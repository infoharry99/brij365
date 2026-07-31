<?php

namespace App\Application\Recruitment\Actions;

use App\Application\Recruitment\Data\RecruitmentWorkspaceData;
use App\Application\Recruitment\Data\RecruitmentPipelineColumnData;
use App\Domain\Recruitment\Services\RecruitmentWorkspaceRegister;
use App\Models\Candidate;
use App\Models\JobOffer;
use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListRecruitmentWorkspace
{
    public function __construct(private readonly RecruitmentWorkspaceRegister $register) {}

    public function execute(
        User $user,
        array $filters,
        string $active,
        ?LengthAwarePaginator $openings = null,
        ?LengthAwarePaginator $candidates = null,
        ?LengthAwarePaginator $interviews = null,
        ?LengthAwarePaginator $offers = null,
    ): RecruitmentWorkspaceData {
        $candidateStages = [
            'screening' => 'Screening',
            'interview_scheduled' => 'Interview scheduled',
            'interviewed' => 'Interviewed',
            'selected' => 'Selected',
            'offer_draft' => 'Offer draft',
            'offer_released' => 'Offer released',
            'employee_created' => 'Employee created',
            'rejected' => 'Rejected',
        ];
        $abilities = [
            'canCreateOpening' => $user->can('create', JobOpening::class),
            'canCreateCandidate' => $user->can('create', Candidate::class),
            'canScheduleInterview' => $user->can('create', Candidate::class),
            'canCreateOffer' => $user->can('create', JobOffer::class),
            'canViewCompensation' => $user->hasPermission('recruitment.manage') || $user->hasPermission('recruitment.approve'),
        ];

        $empty = collect();
        $companies = $branches = $projects = $openOpeningOptions = $activeCandidateOptions = $offerCandidateOptions = $panelUsers = $departments = $sources = $pipelineStages = $empty;

        if ($active === 'openings') {
            $openings = $this->register->presentOpenings($user, $openings ?? $this->register->openings($user, $filters), $abilities['canViewCompensation']);
            $companies = $this->register->companies($user);
            $branches = $this->register->branches($user);
            $projects = $this->register->projects($user);
            $departments = $this->register->departments($user);
        } elseif ($active === 'pipeline') {
            $pipelineStages = $this->register->pipelineStages(
                $user,
                $filters,
                array_keys($candidateStages),
                $abilities['canViewCompensation'],
            );
            $sources = $this->register->sources($user);
        } elseif ($active === 'candidates') {
            $candidates = $this->register->presentCandidates($user, $candidates ?? $this->register->candidates($user, $filters), $abilities['canViewCompensation']);
            $sources = $this->register->sources($user);
            $openOpeningOptions = $this->register->openingOptions($user);
        } elseif ($active === 'interviews') {
            $interviews = $this->register->presentInterviews($user, $interviews ?? $this->register->interviews($user, $filters));
            $activeCandidateOptions = $this->register->candidateOptions($user);
            $panelUsers = $this->register->panelUsers($user);
        } elseif ($active === 'offers') {
            $offers = $this->register->presentOffers($user, $offers ?? $this->register->offers($user, $filters), $abilities['canViewCompensation']);
            $offerCandidateOptions = $this->register->candidateOptions($user, true);
        }

        $summary = $this->register->summary($user);
        $pipelineColumns = [];
        if ($active === 'pipeline') {
            foreach ($pipelineStages as $pipelineStage) {
                $label = $candidateStages[$pipelineStage->stage] ?? $pipelineStage->stage;
                $pipelineColumns[] = new RecruitmentPipelineColumnData(
                    stage: $pipelineStage->stage,
                    label: $label,
                    tone: $this->pipelineTone($pipelineStage->stage),
                    total: $pipelineStage->total,
                    limit: $pipelineStage->limit,
                    candidates: $pipelineStage->candidates,
                );
            }
        }

        return new RecruitmentWorkspaceData(
            activeRegister: $active,
            filters: $filters,
            summary: $summary,
            openings: $openings,
            candidates: $candidates,
            pipelineColumns: $pipelineColumns,
            interviews: $interviews,
            offers: $offers,
            companies: $companies,
            branches: $branches,
            projects: $projects,
            openOpeningOptions: $openOpeningOptions,
            activeCandidateOptions: $activeCandidateOptions,
            offerCandidateOptions: $offerCandidateOptions,
            panelUsers: $panelUsers,
            departments: $departments,
            sources: $sources,
            openingStatuses: ['pending_approval' => 'Pending approval', 'open' => 'Open', 'on_hold' => 'On hold', 'closed' => 'Closed', 'rejected' => 'Rejected'],
            candidateStages: $candidateStages,
            interviewStatuses: ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
            offerStatuses: ['draft' => 'Draft', 'released' => 'Released', 'accepted' => 'Accepted', 'rejected' => 'Rejected'],
            employmentTypes: ['full_time' => 'Full time', 'part_time' => 'Part time', 'contract' => 'Contract', 'intern' => 'Intern', 'consultant' => 'Consultant'],
            interviewModes: ['phone' => 'Phone', 'video' => 'Video', 'in_person' => 'In person'],
            abilities: $abilities,
        );
    }

    private function pipelineTone(string $stage): string
    {
        return match ($stage) {
            'employee_created' => 'is-success',
            'rejected' => 'is-danger',
            'selected', 'offer_draft', 'offer_released' => 'is-warning',
            'interview_scheduled', 'interviewed' => 'is-purple',
            default => 'is-info',
        };
    }
}
