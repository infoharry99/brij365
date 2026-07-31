<?php

namespace App\Domain\Crm\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class MarketingWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string, mixed> $filters */
    public function campaigns(User $user, array $filters): LengthAwarePaginator
    {
        $query = MarketingCampaign::query()->with(['company', 'project', 'createdBy', 'approvedBy']);
        $this->companyScope->apply($query, $user);

        $campaigns = $query
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['channel'] ?? null, fn ($query, $value) => $query->where('channel', $value))
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('start_on', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('start_on', '<=', $value))
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where(fn ($inner) => $inner
                ->where('campaign_code', 'like', "%{$value}%")
                ->orWhere('name', 'like', "%{$value}%")
                ->orWhere('source', 'like', "%{$value}%")))
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));

        $campaigns->getCollection()->transform(fn (MarketingCampaign $campaign) => $this->withCampaignMetrics($campaign));

        return $campaigns;
    }

    /** @param array<string, mixed> $filters */
    public function activities(User $user, array $filters): LengthAwarePaginator
    {
        $query = LeadActivity::query()->with(['lead.customer', 'project', 'actor', 'marketingCampaign']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['lead_id'] ?? null, fn ($query, $value) => $query->where('lead_id', $value))
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['marketing_campaign_id'] ?? null, fn ($query, $value) => $query->where('marketing_campaign_id', $value))
            ->when($filters['activity_type'] ?? null, fn ($query, $value) => $query->where('activity_type', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('activity_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('activity_at', '<=', $value))
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where(fn ($inner) => $inner
                ->where('activity_number', 'like', "%{$value}%")
                ->orWhere('subject', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")))
            ->orderByDesc('activity_at')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    /** @return array<string, int|float> */
    public function campaignSummary(User $user): array
    {
        $query = MarketingCampaign::query();
        $this->companyScope->apply($query, $user);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'budget' => (float) (clone $query)->sum('budget_amount'),
        ];
    }

    public function leads(User $user): Collection
    {
        $query = Lead::query()->with('customer')->orderBy('lead_code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'project_id', 'customer_id', 'lead_code']);
    }

    private function withCampaignMetrics(MarketingCampaign $campaign): MarketingCampaign
    {
        $leads = Lead::query()->where('marketing_campaign_id', $campaign->id);
        $total = (clone $leads)->count();
        $won = (clone $leads)->where('status', 'won')->count();
        $bookings = Booking::query()->whereIn('lead_id', (clone $leads)->select('id'))->count();

        $campaign->setAttribute('metrics', [
            'total_leads' => $total,
            'open_leads' => (clone $leads)->where('status', 'open')->count(),
            'won_leads' => $won,
            'lost_leads' => (clone $leads)->where('status', 'lost')->count(),
            'bookings' => $bookings,
            'expected_value' => (float) (clone $leads)->sum('expected_value'),
            'conversion_rate' => $total > 0 ? round(($won / $total) * 100, 2) : 0.0,
            'lead_target_attainment' => $campaign->target_leads > 0 ? round(($total / $campaign->target_leads) * 100, 2) : null,
            'booking_target_attainment' => $campaign->target_bookings > 0 ? round(($bookings / $campaign->target_bookings) * 100, 2) : null,
        ]);

        return $campaign;
    }
}
