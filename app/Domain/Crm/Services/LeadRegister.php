<?php

namespace App\Domain\Crm\Services;

use App\Models\Lead;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class LeadRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string,mixed> $filters @return LengthAwarePaginator<int,Lead> */
    public function for(User $user, array $filters): LengthAwarePaginator
    {
        $query = Lead::query()->with(['company', 'project', 'customer', 'partner', 'marketingCampaign', 'owner']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when(isset($filters['stage']), fn ($builder) => $builder->where('stage', $filters['stage']))
            ->when(isset($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn ($builder) => $builder->where('project_id', $filters['project_id']))
            ->when(isset($filters['marketing_campaign_id']), fn ($builder) => $builder->where('marketing_campaign_id', $filters['marketing_campaign_id']))
            ->when(isset($filters['source']), fn ($builder) => $builder->where('source', $filters['source']))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }
}
