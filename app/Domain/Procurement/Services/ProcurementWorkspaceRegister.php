<?php

namespace App\Domain\Procurement\Services;

use App\Application\Scoring\Actions\ReadCurrentScores;
use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Procurement\ProcurementService;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ProcurementWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly PaginationPolicy $pagination,
        private readonly ProcurementService $procurement,
        private readonly ReadCurrentScores $scores,
    ) {}

    /** @param array<string, mixed> $filters */
    public function dashboard(User $user, array $filters): array
    {
        return $this->procurement->dashboard($filters, $user);
    }

    /** @param array<string, mixed> $filters */
    public function vendors(User $user, array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = Vendor::query();
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['vendor_type'] ?? null, fn ($query, $value) => $query->where('vendor_type', $value))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$value}%")->orWhere('vendor_code', 'like', "%{$value}%")->orWhere('gstin', 'like', "%{$value}%")))
            ->orderBy('name')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string, mixed> $filters */
    public function requisitions(User $user, array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = PurchaseRequisition::query()->with(['project', 'requestedBy', 'approvedBy']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->orderByDesc('created_at')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

    /** @param array<string, mixed> $filters */
    public function purchaseOrders(User $user, array $filters): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['project', 'vendor', 'purchaseRequisition', 'createdBy', 'approvedBy', 'goodsReceipts']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['vendor_id'] ?? null, fn ($query, $value) => $query->where('vendor_id', $value))
            ->orderByDesc('created_at')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    /** @param array<string, mixed> $filters */
    public function goodsReceipts(User $user, array $filters): LengthAwarePaginator
    {
        $query = GoodsReceipt::query()->with(['project', 'purchaseOrder', 'receivedBy']);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['purchase_order_id'] ?? null, fn ($query, $value) => $query->where('purchase_order_id', $value))
            ->orderByDesc('received_on')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    /** @param array<string, mixed> $filters */
    public function stockItems(User $user, array $filters, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = StockItem::query()->with(['project', 'movements' => fn ($query) => $query->latest('movement_date')->latest()]);
        $this->companyScope->apply($query, $user);

        return $query
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['store_type'] ?? null, fn ($query, $value) => $query->where('store_type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['item_code'] ?? null, fn ($query, $value) => $query->where('item_code', 'like', '%'.strtoupper($value).'%'))
            ->when(($filters['low_stock'] ?? false) === true, fn ($query) => $query->whereColumn('on_hand_quantity', '<=', 'minimum_stock_quantity')->where('minimum_stock_quantity', '>', 0))
            ->orderBy('item_code')->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null), ['*'], $pageName);
    }

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

    /** @return array<int, mixed> */
    public function vendorScores(User $user, LengthAwarePaginator $vendors): array
    {
        $companyId = $this->companyScope->companyIdFor($user);

        return $companyId === null ? [] : $this->scores->execute($companyId, 'vendor_performance', Vendor::class, $vendors->getCollection()->modelKeys());
    }
}
