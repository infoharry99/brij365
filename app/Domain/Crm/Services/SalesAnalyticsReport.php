<?php

namespace App\Domain\Crm\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Builder;

final class SalesAnalyticsReport
{
    public function __construct(private readonly CompanyScopeService $companyScope) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function for(User $user, array $filters): array
    {
        $leads = $this->leads($user, $filters);
        $leadIds = (clone $leads)->select('leads.id');
        $total = (clone $leads)->count();
        $qualified = (clone $leads)->whereIn('stage', ['Qualified', 'Site Visit Planned', 'Negotiation', 'Booked'])->count();
        $visits = SiteVisit::query()->whereIn('lead_id', clone $leadIds)->distinct('lead_id')->count('lead_id');
        $bookings = Booking::query()->whereIn('lead_id', clone $leadIds)->where('status', 'confirmed')->count();

        return [
            'summary' => [
                'leads' => $total,
                'qualified' => $qualified,
                'site_visits' => $visits,
                'bookings' => $bookings,
                'qualification_rate' => $this->percentage($qualified, $total),
                'booking_conversion' => $this->percentage($bookings, $total),
            ],
            'funnel' => [
                ['label' => 'Total Leads', 'value' => $total, 'rate' => 100.0],
                ['label' => 'Qualified', 'value' => $qualified, 'rate' => $this->percentage($qualified, $total)],
                ['label' => 'Site Visits', 'value' => $visits, 'rate' => $this->percentage($visits, $total)],
                ['label' => 'Bookings', 'value' => $bookings, 'rate' => $this->percentage($bookings, $total)],
            ],
            'sources' => $this->sourcePerformance($leads),
            'team' => $this->teamPerformance($leads),
            'projects' => $this->projectPerformance($leads),
            'campaigns' => $this->campaignPerformance($user, $filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function leads(User $user, array $filters): Builder
    {
        $query = Lead::query();
        $this->companyScope->apply($query, $user, 'leads.company_id');

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('leads.project_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('leads.created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('leads.created_at', '<=', $value));
    }

    /** @return array<int, array<string, mixed>> */
    private function sourcePerformance(Builder $leads): array
    {
        return (clone $leads)
            ->selectRaw("COALESCE(source, 'Unspecified') as label, COUNT(*) as total, SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won")
            ->groupBy('source')->orderByDesc('total')->get()
            ->map(fn ($row) => ['label' => $row->label, 'leads' => (int) $row->total, 'won' => (int) $row->won, 'conversion' => $this->percentage((int) $row->won, (int) $row->total)])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function teamPerformance(Builder $leads): array
    {
        return (clone $leads)
            ->leftJoin('users', 'users.id', '=', 'leads.owner_user_id')
            ->selectRaw("COALESCE(users.name, 'Unassigned') as label, COUNT(leads.id) as total, SUM(CASE WHEN leads.status = 'won' THEN 1 ELSE 0 END) as won, COALESCE(SUM(leads.expected_value), 0) as pipeline")
            ->groupBy('leads.owner_user_id', 'users.name')->orderByDesc('won')->orderByDesc('total')->get()
            ->map(fn ($row) => ['label' => $row->label, 'leads' => (int) $row->total, 'won' => (int) $row->won, 'pipeline' => (float) $row->pipeline, 'conversion' => $this->percentage((int) $row->won, (int) $row->total)])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function projectPerformance(Builder $leads): array
    {
        return (clone $leads)
            ->leftJoin('projects', 'projects.id', '=', 'leads.project_id')
            ->selectRaw("COALESCE(projects.code, 'Unassigned') as code, COALESCE(projects.name, 'No project') as label, COUNT(leads.id) as total, SUM(CASE WHEN leads.status = 'won' THEN 1 ELSE 0 END) as won")
            ->groupBy('leads.project_id', 'projects.code', 'projects.name')->orderByDesc('total')->get()
            ->map(fn ($row) => ['code' => $row->code, 'label' => $row->label, 'leads' => (int) $row->total, 'won' => (int) $row->won, 'conversion' => $this->percentage((int) $row->won, (int) $row->total)])
            ->all();
    }

    /** @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
    private function campaignPerformance(User $user, array $filters): array
    {
        $query = MarketingCampaign::query()->withCount([
            'leads',
            'leads as won_leads_count' => fn ($query) => $query->where('status', 'won'),
        ]);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('start_on', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('start_on', '<=', $value))
            ->orderByDesc('leads_count')->limit(20)->get()
            ->map(fn (MarketingCampaign $campaign) => [
                'code' => $campaign->campaign_code,
                'label' => $campaign->name,
                'source' => $campaign->source,
                'status' => $campaign->status,
                'leads' => (int) $campaign->leads_count,
                'won' => (int) $campaign->won_leads_count,
                'conversion' => $this->percentage((int) $campaign->won_leads_count, (int) $campaign->leads_count),
            ])->all();
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}
