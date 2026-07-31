<?php

namespace App\Domain\Administration\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Domain\Scoring\Support\LogicCenterPermissions;

final class AdministrationRegister
{
    public function __construct(private readonly PaginationPolicy $pagination) {}

    public function users(User $actor, array $filters): LengthAwarePaginator
    {
        $query = User::query()->with(['role', 'company', 'employee'])
            ->when($filters['company_id'] ?? null, fn ($q, int $id) => $q->where('company_id', $id))
            ->when($filters['role_id'] ?? null, fn ($q, int $id) => $q->where('role_id', $id))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, string $term): void {
                $like = '%'.$term.'%';
                $q->where(fn ($nested) => $nested->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })->orderBy('name');

        if (! $actor->hasPermission('*')) {
            $query->where('company_id', $actor->company_id ?: 0);
        }

        return $query->paginate($this->pagination->workspacePerPage())->withQueryString();
    }

    public function roles(array $filters): LengthAwarePaginator
    {
        return Role::query()->withCount('users')
            ->when($filters['scope_level'] ?? null, fn ($q, string $scope) => $q->where('scope_level', $scope))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when($filters['search'] ?? null, function ($q, string $term): void {
                $like = '%'.$term.'%';
                $q->where(fn ($nested) => $nested->where('slug', 'like', $like)->orWhere('name', 'like', $like));
            })->orderBy('name')->paginate($this->pagination->workspacePerPage())->withQueryString();
    }

    public function companies(): LengthAwarePaginator
    {
        return Company::query()->withCount(['branches', 'projects', 'users'])->orderBy('name')
            ->paginate($this->pagination->workspacePerPage())->withQueryString();
    }

    public function companyOptions(User $actor): Collection
    {
        return Company::query()->when(! $actor->hasPermission('*'), fn ($q) => $q->whereKey($actor->company_id ?: 0))
            ->orderBy('name')->get();
    }

    public function assignableRoles(User $actor): Collection
    {
        return Role::query()->where('is_active', true)->orderBy('name')->get()
            ->filter(fn (Role $role): bool => $actor->hasPermission('*') || (! in_array('*', $role->permissions ?? [], true) && $role->scope_level !== 'global'))
            ->values();
    }

    public function permissionCatalog(User $actor): Collection
    {
        $permissions = array_merge(['*','after_sales.approve','after_sales.manage','after_sales.view','assets.manage','assets.view','attendance.approve','attendance.manage','attendance.request','attendance.view','audit.view','booking.manage','booking.view','buyer.view','claims.approve','claims.manage','claims.view','collections.approve','collections.manage','collections.view','collaboration.manage','collaboration.view','construction.approve','construction.manage','construction.view','crm.manage','crm.view','dashboard.view','documents.approve','documents.manage','documents.view','employee.self_service','finance.approve','finance.manage','finance.view','helpdesk.manage','helpdesk.view','hr.manage','hr.view','inventory.view','leave.approve','leave.manage','leave.request','leave.view','legal.approve','legal.manage','legal.view','loans.approve','loans.manage','loans.view','maintenance.manage','maintenance.view','partner.portal','payroll.approve','payroll.manage','payroll.view','performance.approve','performance.manage','performance.view','possession.approve','possession.manage','possession.view','procurement.approve','procurement.manage','procurement.view','recruitment.approve','recruitment.manage','recruitment.view','reports.manage','reports.view','roles.manage','roles.view','sales.manage','sales.view','settings.approve','settings.manage','settings.view','users.manage','users.view'], LogicCenterPermissions::all());

        return collect($permissions)->filter(fn (string $permission): bool => $actor->hasPermission('*') || $actor->hasPermission($permission))->values();
    }
}
