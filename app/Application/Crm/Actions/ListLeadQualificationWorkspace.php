<?php

namespace App\Application\Crm\Actions;

use App\Application\Crm\Data\LeadQualificationWorkspaceData;
use App\Application\Scoring\Actions\ReadCurrentScores;
use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Crm\LeadEngagementService;
use App\Services\Crm\LeadQualityScoreService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;

final class ListLeadQualificationWorkspace
{
    public function __construct(
        private readonly LeadEngagementService $engagement,
        private readonly CompanyScopeService $companyScope,
        private readonly LeadQualityScoreService $quality,
        private readonly ReadCurrentScores $scores,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): LeadQualificationWorkspaceData
    {
        $query = LeadQualification::query()->with($this->engagement->qualificationRelations());
        $this->companyScope->apply($query, $user);
        $qualifications = $query
            ->when(isset($filters['lead_id']), fn ($builder) => $builder->where('lead_id', $filters['lead_id']))
            ->when(isset($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(isset($filters['min_score']), fn ($builder) => $builder->where('score', '>=', $filters['min_score']))
            ->when(isset($filters['expected_from']), fn ($builder) => $builder->whereDate('expected_booking_date', '>=', $filters['expected_from']))
            ->when(isset($filters['expected_to']), fn ($builder) => $builder->whereDate('expected_booking_date', '<=', $filters['expected_to']))
            ->latest()->paginate($this->pagination->workspacePerPage());

        $leadQuery = Lead::query()->with(['customer:id,name,email,phone', 'project:id,code,name'])
            ->whereNotIn('status', ['lost'])->orderByDesc('created_at');
        $this->companyScope->apply($leadQuery, $user);
        $companyId = $this->companyScope->companyIdFor($user);

        return new LeadQualificationWorkspaceData(
            qualifications: $qualifications,
            filters: $filters,
            leads: $leadQuery->limit(100)->get(),
            rules: $this->quality->rulesForCompany($companyId),
            statuses: ['qualified' => 'Qualified', 'nurture' => 'Nurture', 'disqualified' => 'Disqualified'],
            canQualify: $user->can('create', LeadQualification::class),
            canManageScoring: $user->can('create', ScoringRule::class),
            scoringUrl: route('scoring.index'),
            leadScores: $companyId === null ? [] : $this->scores->execute($companyId, 'lead_quality', Lead::class, $qualifications->getCollection()->pluck('lead_id')->unique()->values()->all()),
        );
    }
}
