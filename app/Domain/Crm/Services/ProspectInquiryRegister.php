<?php

namespace App\Domain\Crm\Services;

use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ProspectInquiryRegister
{
    public function __construct(private readonly CompanyScopeService $companyScope, private readonly PaginationPolicy $pagination) {}

    /** @param array<string,mixed> $filters @return LengthAwarePaginator<int,ProspectInquiry> */
    public function for(User $user, array $filters): LengthAwarePaginator
    {
        $query = ProspectInquiry::query()->with(['company', 'project', 'assignedTo', 'convertedLead', 'duplicateOf']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when(isset($filters['status']), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn ($builder) => $builder->where('project_id', $filters['project_id']))
            ->when(isset($filters['assigned_to_user_id']), fn ($builder) => $builder->where('assigned_to_user_id', $filters['assigned_to_user_id']))
            ->when(isset($filters['source']), fn ($builder) => $builder->where('source', $filters['source']))
            ->when(isset($filters['channel']), fn ($builder) => $builder->where('channel', $filters['channel']))
            ->when(isset($filters['created_from']), fn ($builder) => $builder->whereDate('created_at', '>=', $filters['created_from']))
            ->when(isset($filters['created_to']), fn ($builder) => $builder->whereDate('created_at', '<=', $filters['created_to']))
            ->when(isset($filters['q']), fn ($builder) => $builder->where(function ($nested) use ($filters): void {
                $term = '%'.$filters['q'].'%';
                $nested->where('inquiry_number', 'like', $term)->orWhere('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term);
            }))
            ->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function assignees(User $user): Collection
    {
        $query = User::query()->with('role')->where('status', 'active')->orderBy('name');
        $this->companyScope->apply($query, $user);

        return $query->get(['id', 'role_id', 'company_id', 'name', 'email'])
            ->filter(fn (User $option): bool => $option->hasPermission('crm.manage') || $option->hasPermission('crm.view'))->values();
    }

    public function sources(User $user): Collection
    {
        $query = ProspectInquiry::query()->select('source')->whereNotNull('source')->distinct()->orderBy('source');
        $this->companyScope->apply($query, $user);
        return collect(['Website', 'Referral', 'Broker network', 'Walk-in', 'Google Ads', 'Facebook', 'MagicBricks', '99acres'])->merge($query->pluck('source'))->filter()->unique()->values();
    }

    public function channels(User $user): Collection
    {
        $query = ProspectInquiry::query()->select('channel')->whereNotNull('channel')->distinct()->orderBy('channel');
        $this->companyScope->apply($query, $user);
        return collect(['website', 'mobile', 'phone', 'email', 'walk_in', 'partner'])->merge($query->pluck('channel'))->filter()->unique()->values();
    }

    public function metrics(User $user): Collection
    {
        $query = ProspectInquiry::query()->selectRaw('status, count(*) as aggregate')->groupBy('status');
        $this->companyScope->apply($query, $user);
        return $query->pluck('aggregate', 'status');
    }
}
