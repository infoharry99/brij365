<?php

namespace App\Domain\Crm\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Collection;

final class CrmWorkspaceOptions
{
    public function __construct(private readonly CompanyScopeService $companyScope) {}

    public function companies(User $user): Collection
    {
        $query = Company::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($query, $user, 'id');

        return $query->get(['id', 'code', 'name']);
    }

    public function projects(User $user): Collection
    {
        $query = Project::query()->where('status', 'active')->orderBy('code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'code', 'name']);
    }

    public function campaigns(User $user): Collection
    {
        $query = MarketingCampaign::query()->whereIn('status', ['active', 'draft'])->orderBy('campaign_code');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'company_id', 'project_id', 'campaign_code', 'name', 'source', 'status']);
    }

    public function partners(User $user): Collection
    {
        return Partner::query()
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'partner_type']);
    }

    public function leadSources(User $user): Collection
    {
        $query = Lead::query()->select('source')->whereNotNull('source')->distinct()->orderBy('source');
        $this->companyScope->apply($query, $user);

        return collect(['Channel walk-in', 'Referral', 'Broker network', 'Walk-in', 'Google Ads', 'Facebook', 'MagicBricks', '99acres'])
            ->merge($query->pluck('source'))->filter()->unique()->values();
    }

    public function stages(): array
    {
        return ['New', 'Qualified', 'Site Visit Planned', 'Negotiation', 'Booked', 'Lost'];
    }

    public function statuses(): array
    {
        return ['open' => 'Open', 'won' => 'Won', 'lost' => 'Lost', 'on_hold' => 'On hold'];
    }
}
