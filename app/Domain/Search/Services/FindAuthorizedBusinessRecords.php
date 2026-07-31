<?php

namespace App\Domain\Search\Services;

use App\Application\Search\DTOs\SearchGroupData;
use App\Application\Search\DTOs\SearchResultData;
use App\Models\FinancialVoucher;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Support\Facades\Gate;

final class FindAuthorizedBusinessRecords
{
    private const LIMIT_PER_GROUP = 8;

    public function __construct(private readonly CompanyScopeService $companyScope)
    {
    }

    /** @return list<SearchGroupData> */
    public function forUser(User $user, string $search): array
    {
        $pattern = $this->containsPattern($search);
        $groups = [];

        if (Gate::forUser($user)->allows('viewAny', Project::class)) {
            $results = $this->companyScope->apply(Project::query(), $user)
                ->where(function ($query) use ($pattern): void {
                    $query->where('code', 'like', $pattern)
                        ->orWhere('name', 'like', $pattern)
                        ->orWhere('city', 'like', $pattern);
                })
                ->orderBy('code')
                ->limit(self::LIMIT_PER_GROUP)
                ->get(['id', 'code', 'name', 'city', 'status'])
                ->map(static fn (Project $project): SearchResultData => new SearchResultData(
                    key: 'project-'.$project->getKey(),
                    title: $project->code.' · '.$project->name,
                    subtitle: implode(' · ', array_filter([$project->city, str($project->status)->headline()->toString()])),
                    url: route('projects.index', ['search' => $project->code]),
                    icon: 'fa-building',
                ))
                ->all();

            $groups[] = new SearchGroupData('projects', 'Projects', $results);
        }

        if (Gate::forUser($user)->allows('viewAny', ProjectUnit::class)) {
            $results = $this->companyScope->apply(ProjectUnit::query()->with('project:id,code,name'), $user)
                ->where(function ($query) use ($pattern): void {
                    $query->where('unit_code', 'like', $pattern)
                        ->orWhere('unit_number', 'like', $pattern)
                        ->orWhere('tower', 'like', $pattern)
                        ->orWhere('unit_type', 'like', $pattern);
                })
                ->orderBy('unit_code')
                ->limit(self::LIMIT_PER_GROUP)
                ->get(['id', 'project_id', 'unit_code', 'unit_number', 'tower', 'unit_type', 'status'])
                ->map(static fn (ProjectUnit $unit): SearchResultData => new SearchResultData(
                    key: 'unit-'.$unit->getKey(),
                    title: $unit->unit_code,
                    subtitle: implode(' · ', array_filter([
                        $unit->project?->code,
                        $unit->tower ? 'Tower '.$unit->tower : null,
                        $unit->unit_type,
                        str($unit->status)->headline()->toString(),
                    ])),
                    url: route('inventory.units.index', array_filter(['project_id' => $unit->project_id])),
                    icon: 'fa-door-open',
                ))
                ->all();

            $groups[] = new SearchGroupData('units', 'Units', $results);
        }

        if (Gate::forUser($user)->allows('viewAny', Lead::class)) {
            $results = $this->companyScope->apply(
                Lead::query()->with(['customer:id,name', 'project:id,code,name']),
                $user,
            )
                ->where(function ($query) use ($pattern): void {
                    $query->where('lead_code', 'like', $pattern)
                        ->orWhere('source', 'like', $pattern)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $pattern));
                })
                ->latest()
                ->limit(self::LIMIT_PER_GROUP)
                ->get(['id', 'project_id', 'customer_id', 'lead_code', 'source', 'stage', 'status'])
                ->map(static fn (Lead $lead): SearchResultData => new SearchResultData(
                    key: 'lead-'.$lead->getKey(),
                    title: $lead->lead_code.' · '.($lead->customer?->name ?? 'Lead'),
                    subtitle: implode(' · ', array_filter([$lead->project?->code, $lead->stage, $lead->source])),
                    url: route('crm.leads.index', array_filter(['project_id' => $lead->project_id])),
                    icon: 'fa-user-group',
                ))
                ->all();

            $groups[] = new SearchGroupData('leads', 'Leads', $results);
        }

        if (Gate::forUser($user)->allows('viewAny', FinancialVoucher::class)) {
            $results = $this->companyScope->apply(
                FinancialVoucher::query()->with('project:id,code,name'),
                $user,
            )
                ->where(function ($query) use ($pattern): void {
                    $query->where('voucher_number', 'like', $pattern)
                        ->orWhere('reference_number', 'like', $pattern)
                        ->orWhere('narration', 'like', $pattern);
                })
                ->latest('voucher_date')
                ->limit(self::LIMIT_PER_GROUP)
                ->get(['id', 'project_id', 'voucher_number', 'voucher_type', 'status', 'voucher_date', 'reference_number'])
                ->map(static fn (FinancialVoucher $voucher): SearchResultData => new SearchResultData(
                    key: 'voucher-'.$voucher->getKey(),
                    title: $voucher->voucher_number,
                    subtitle: implode(' · ', array_filter([
                        $voucher->project?->code,
                        str($voucher->voucher_type)->headline()->toString(),
                        str($voucher->status)->headline()->toString(),
                    ])),
                    url: route('finance.vouchers.index', ['q' => $voucher->voucher_number]),
                    icon: 'fa-receipt',
                ))
                ->all();

            $groups[] = new SearchGroupData('vouchers', 'Vouchers', $results);
        }

        return $groups;
    }

    private function containsPattern(string $search): string
    {
        return '%'.addcslashes(trim($search), '\\%_').'%';
    }
}
