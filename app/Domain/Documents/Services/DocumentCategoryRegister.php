<?php

namespace App\Domain\Documents\Services;

use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class DocumentCategoryRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function categories(User $actor, array $filters): LengthAwarePaginator
    {
        $companyId = $this->companyScope->companyIdFor($actor);

        return DocumentCategory::query()
            ->with('company:id,code,name')
            ->withCount('documents')
            ->where('is_active', true)
            ->where(function ($query) use ($actor, $companyId): void {
                $query->whereNull('company_id');

                if ($this->companyScope->hasUnrestrictedCompanyScope($actor)) {
                    $query->orWhereNotNull('company_id');
                } elseif ($companyId !== null && $companyId > 0) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->when($filters['owner_type'] ?? null, fn ($query, string $ownerType) => $query->where('owner_type', $ownerType))
            ->orderBy('owner_type')
            ->orderBy('name')
            ->paginate($this->pagination->largePerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }
}
