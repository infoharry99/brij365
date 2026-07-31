<?php

namespace App\Domain\Settings\Services;

use App\Models\Company;
use App\Models\DataImportBatch;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SettingsRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination) {}

    public function settings(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->settingsQuery($actor)->with(['company', 'createdBy', 'approvedBy'])
            ->when($filters['setting_group'] ?? null, fn ($q, string $value) => $q->where('setting_group', $value))
            ->when($filters['setting_key'] ?? null, fn ($q, string $value) => $q->where('setting_key', $value))
            ->when($filters['status'] ?? null, fn ($q, string $value) => $q->where('status', $value))
            ->when($filters['scope_key'] ?? null, fn ($q, string $value) => $q->where('scope_key', $value))
            ->orderBy('setting_group')->orderBy('setting_key')->orderByDesc('version')
            ->paginate($this->pagination->workspacePerPage())->withQueryString();
    }

    public function imports(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(DataImportBatch::query()->with(['company', 'createdBy', 'postedBy']), $actor)
            ->when($filters['company_id'] ?? null, fn ($q, int $id) => $q->where('company_id', $id))
            ->when($filters['import_type'] ?? null, fn ($q, string $type) => $q->where('import_type', $type))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest()->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))->withQueryString();
    }

    public function companies(User $actor): Collection
    {
        return $this->scope->apply(Company::query(), $actor, 'id')->orderBy('name')->get();
    }

    public function groups(User $actor): Collection
    {
        return $this->settingsQuery($actor)->select('setting_group')->distinct()->orderBy('setting_group')->pluck('setting_group');
    }

    public function keys(User $actor): Collection
    {
        return $this->settingsQuery($actor)->select('setting_key')->distinct()->orderBy('setting_key')->pluck('setting_key');
    }

    private function settingsQuery(User $actor): Builder
    {
        $query = SystemSetting::query();

        $companyId = $this->scope->settingCompanyIdFor($actor);
        if ($companyId === 0) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyId === null) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->whereNull('company_id')
            ->orWhere('company_id', $companyId));
    }
}
