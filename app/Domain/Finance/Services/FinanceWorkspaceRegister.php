<?php

namespace App\Domain\Finance\Services;

use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\Company;
use App\Models\Customer;
use App\Models\FinancialVoucher;
use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class FinanceWorkspaceRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
    ) {}

    public function companies(User $actor): Collection
    {
        $companyId = $this->scope->companyIdFor($actor);

        return Company::query()
            ->when($companyId !== null, fn ($query) => $query->whereKey($companyId))
            ->where('status', 'active')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    public function projects(User $actor, array $columns = ['id', 'company_id', 'code', 'name', 'status']): Collection
    {
        return $this->scope->apply(Project::query()->select($columns), $actor)->orderBy('code')->get();
    }

    public function receipts(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->receiptQuery($actor, $filters)
            ->latest('receipt_date')
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function receiptQuery(User $actor, array $filters): Builder
    {
        return $this->scope->apply(
            CollectionReceipt::query()->with(['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectedBy', 'approvedBy']),
            $actor,
        )
            ->when($filters['booking_id'] ?? null, fn (Builder $query, int $id) => $query->where('booking_id', $id))
            ->when($filters['project_id'] ?? null, fn (Builder $query, int $id) => $query->where('project_id', $id))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $id) => $query->where('customer_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_mode'] ?? null, fn (Builder $query, string $mode) => $query->where('payment_mode', $mode))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('receipt_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('receipt_date', '<=', $date));
    }

    public function bookings(User $actor): Collection
    {
        return $this->scope->apply(
            Booking::query()->with(['project:id,code,name', 'customer:id,code,name,email,phone', 'unit:id,unit_code', 'paymentSchedules']),
            $actor,
        )
            ->whereIn('status', ['confirmed', 'agreement_pending', 'registered'])
            ->orderBy('booking_code')
            ->get();
    }

    public function customers(User $actor): Collection
    {
        return Customer::query()
            ->where(function ($query) use ($actor): void {
                $query->whereHas('bookings', fn ($bookingQuery) => $this->scope->apply($bookingQuery, $actor))
                    ->orWhereHas('collectionReceipts', fn ($receiptQuery) => $this->scope->apply($receiptQuery, $actor));
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'email', 'phone']);
    }

    public function vouchers(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(
            FinancialVoucher::query()->with(['company', 'project', 'createdBy', 'approvedBy', 'lines.project']),
            $actor,
        )
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['voucher_type'] ?? null, fn ($query, string $type) => $query->where('voucher_type', $type))
            ->when($filters['project_id'] ?? null, fn ($query, int $id) => $query->where('project_id', $id))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('voucher_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('voucher_date', '<=', $date))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(fn ($nested) => $nested->where('voucher_number', 'like', $like)
                    ->orWhere('reference_number', 'like', $like)->orWhere('narration', 'like', $like));
            })
            ->latest('voucher_date')->latest()
            ->paginate($this->pagination->workspacePerPage())
            ->withQueryString();
    }

    public function paymentRequests(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(
            PaymentRequest::query()->with(['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectionReceipt', 'createdBy', 'paidBy']),
            $actor,
        )
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['booking_id'] ?? null, fn ($query, int $id) => $query->where('booking_id', $id))
            ->when($filters['project_id'] ?? null, fn ($query, int $id) => $query->where('project_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, int $id) => $query->where('customer_id', $id))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(fn ($nested) => $nested->where('request_number', 'like', $like)
                    ->orWhere('gateway_reference', 'like', $like)->orWhere('purpose', 'like', $like));
            })
            ->latest()
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function paymentRequestBookings(User $actor): Collection
    {
        return $this->scope->apply(
            Booking::query()->with([
                'project:id,code,name', 'unit:id,unit_code', 'customer:id,code,name,email',
                'paymentSchedules:id,booking_id,sequence,milestone,amount,due_on,status',
            ])->whereIn('status', ['confirmed', 'agreement_pending', 'registered']),
            $actor,
        )->orderByDesc('booked_on')->orderBy('booking_code')->limit(100)->get();
    }

    public function gstEntries(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(GstEntry::query()->with(['project', 'createdBy', 'approvedBy']), $actor)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['transaction_type'] ?? null, fn ($query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['period_year'] ?? null, fn ($query, int $year) => $query->where('period_year', $year))
            ->when($filters['period_month'] ?? null, fn ($query, int $month) => $query->where('period_month', $month))
            ->when($filters['project_id'] ?? null, fn ($query, int $id) => $query->where('project_id', $id))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $like = '%'.$term.'%';
                $query->where(fn ($nested) => $nested->where('entry_number', 'like', $like)
                    ->orWhere('document_number', 'like', $like)->orWhere('party_name', 'like', $like)->orWhere('party_gstin', 'like', $like));
            })
            ->latest('document_date')->latest()
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }

    public function gstReturnPeriods(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(GstReturnPeriod::query()->with(['company', 'preparedBy', 'approvedBy', 'lockedBy']), $actor)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['period_year'] ?? null, fn ($query, int $year) => $query->where('period_year', $year))
            ->when($filters['period_month'] ?? null, fn ($query, int $month) => $query->where('period_month', $month))
            ->latest('period_start')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null))
            ->withQueryString();
    }
}
