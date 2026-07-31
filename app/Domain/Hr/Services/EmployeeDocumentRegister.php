<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\EmployeeDocumentRowData;
use App\Application\Hr\Data\EmployeeDocumentSummaryData;
use App\Models\Employee;
use App\Models\DocumentCategory;
use App\Models\ManagedDocument;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EmployeeDocumentRegister
{
    public function __construct(private readonly CompanyScopeService $scope, private readonly PaginationPolicy $pagination) {}

    public function companyDocuments(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->documentsScope($actor, $filters)
            ->with(['company', 'category', 'uploadedBy', 'approvedBy', 'employeeOwner'])
            ->orderByDesc('is_current')->orderByRaw('expires_on is null')->orderBy('expires_on')->orderByDesc('created_at')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function employeeDocuments(Employee $employee, array $filters): LengthAwarePaginator
    {
        return ManagedDocument::query()
            ->with(['company', 'category', 'uploadedBy', 'approvedBy', 'employeeOwner'])
            ->where('owner_type', 'employee')
            ->where('owner_id', $employee->id)
            ->where('company_id', $employee->company_id)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['current_only'] ?? true, fn (Builder $query) => $query->where('is_current', true))
            ->when($filters['expires_within_days'] ?? null, fn (Builder $query, int $days) => $query->whereNotNull('expires_on')->whereDate('expires_on', '>=', now()->toDateString())->whereDate('expires_on', '<=', now()->addDays($days)->toDateString()))
            ->orderByDesc('is_current')->orderByRaw('expires_on is null')->orderBy('expires_on')->orderByDesc('created_at')
            ->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    public function summary(User $actor, array $filters, ?Employee $employee = null): EmployeeDocumentSummaryData
    {
        $summaryFilters = $filters;
        unset($summaryFilters['status'], $summaryFilters['page'], $summaryFilters['per_page']);

        $query = $employee === null
            ? $this->documentsScope($actor, $summaryFilters)
            : ManagedDocument::query()
                ->where('company_id', $employee->company_id)
                ->where('owner_type', 'employee')
                ->where('owner_id', $employee->id)
                ->when($summaryFilters['current_only'] ?? true, fn (Builder $builder) => $builder->where('is_current', true))
                ->when($summaryFilters['expires_within_days'] ?? null, fn (Builder $builder, int $days) => $builder->whereNotNull('expires_on')->whereDate('expires_on', '>=', now()->toDateString())->whereDate('expires_on', '<=', now()->addDays($days)->toDateString()));

        $today = now()->toDateString();
        $soon = now()->addDays(30)->toDateString();
        $row = $query
            ->selectRaw('COUNT(*) as aggregate_total')
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as aggregate_submitted")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as aggregate_approved")
            ->selectRaw('SUM(CASE WHEN expires_on >= ? AND expires_on <= ? THEN 1 ELSE 0 END) as aggregate_expiring', [$today, $soon])
            ->selectRaw('SUM(CASE WHEN expires_on < ? THEN 1 ELSE 0 END) as aggregate_expired', [$today])
            ->first();

        return new EmployeeDocumentSummaryData(
            total: (int) ($row?->aggregate_total ?? 0),
            submitted: (int) ($row?->aggregate_submitted ?? 0),
            approved: (int) ($row?->aggregate_approved ?? 0),
            expiringSoon: (int) ($row?->aggregate_expiring ?? 0),
            expired: (int) ($row?->aggregate_expired ?? 0),
        );
    }

    public function present(User $actor, LengthAwarePaginator $documents): LengthAwarePaginator
    {
        return $documents->through(function (ManagedDocument $document) use ($actor): EmployeeDocumentRowData {
            $employee = $document->employeeOwner;
            $expiresOn = $document->expires_on;
            $expired = $expiresOn?->isPast() === true;
            $expiring = ! $expired && $expiresOn?->lte(now()->addDays(30)) === true;

            return new EmployeeDocumentRowData(
                id: $document->id,
                employeeId: (int) $document->owner_id,
                documentNumber: $document->document_number,
                title: $document->title,
                employeeCode: $employee?->employee_code ?? 'No employee code',
                employeeName: $employee?->name ?? 'Employee unavailable',
                employeeInitial: Str::of($employee?->name ?? 'Employee')->trim()->substr(0, 1)->upper()->toString(),
                employeeContext: $employee?->department ?: 'No department',
                category: $document->category?->name ?? 'Uncategorized',
                version: (int) $document->version,
                isCurrent: (bool) $document->is_current,
                issueDate: $document->issue_date?->format('d M Y') ?? 'Not recorded',
                expiryDate: $expiresOn?->format('d M Y') ?? 'No expiry',
                expiryState: $expired ? 'Expired' : ($expiring ? 'Due within 30 days' : ($expiresOn ? 'Current' : 'No expiry')),
                expiryTone: $expired ? 'is-danger' : ($expiring ? 'is-warning' : 'is-muted'),
                filename: $document->original_filename,
                fileSize: $this->fileSize((int) $document->file_size_bytes),
                status: $document->status,
                statusLabel: ucfirst($document->status),
                statusTone: $this->statusTone($document->status),
                canApprove: $employee !== null && $actor->can('update', $employee) && $actor->can('approve', $document),
                canDownload: $actor->can('view', $document),
            );
        });
    }

    public function employees(User $actor): Collection
    {
        $query = Employee::query()->orderBy('name');
        $this->scope->apply($query, $actor);

        return $query->get(['id', 'employee_code', 'name', 'designation', 'department', 'company_id']);
    }

    public function categories(User $actor): Collection
    {
        $companyId = $this->scope->companyIdFor($actor);

        return DocumentCategory::query()
            ->where('is_active', true)
            ->whereIn('owner_type', ['employee', 'global'])
            ->where(function ($query) use ($actor, $companyId): void {
                $query->whereNull('company_id');
                if ($this->scope->hasUnrestrictedCompanyScope($actor)) {
                    $query->orWhereNotNull('company_id');
                } elseif ($companyId !== null) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'owner_type', 'expiry_required', 'company_id']);
    }

    private function documentsScope(User $actor, array $filters): Builder
    {
        return $this->scope->apply(ManagedDocument::query(), $actor)
            ->where('owner_type', 'employee')
            ->when($filters['employee_id'] ?? null, fn (Builder $query, int $employeeId) => $query->where('owner_id', $employeeId))
            ->when($filters['document_category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('document_category_id', $categoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['current_only'] ?? true, fn (Builder $query) => $query->where('is_current', true))
            ->when($filters['expires_within_days'] ?? null, fn (Builder $query, int $days) => $query->whereNotNull('expires_on')->whereDate('expires_on', '>=', now()->toDateString())->whereDate('expires_on', '<=', now()->addDays($days)->toDateString()))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(fn (Builder $searchQuery) => $searchQuery
                    ->where('document_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('original_filename', 'like', $like)
                    ->orWhereHas('employeeOwner', fn (Builder $employeeQuery) => $employeeQuery
                        ->where('employee_code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('department', 'like', $like)));
            });
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'approved' => 'is-success',
            'submitted' => 'is-warning',
            'rejected' => 'is-danger',
            default => 'is-muted',
        };
    }

    private function fileSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }

        return number_format(max(0, $bytes) / 1024, 1).' KB';
    }
}
