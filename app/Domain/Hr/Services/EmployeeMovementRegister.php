<?php

namespace App\Domain\Hr\Services;

use App\Models\Employee;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;

final class EmployeeMovementRegister
{
    public function __construct(private readonly PaginationPolicy $pagination) {}

    public function all(Employee $employee, array $filters): LengthAwarePaginator
    {
        return $employee->movements()->with(['employee', 'company', 'createdBy', 'approvedBy'])->when($filters['status'] ?? null, fn ($q, string $v) => $q->where('status', $v))->when($filters['movement_type'] ?? null, fn ($q, string $v) => $q->where('movement_type', $v))->latest('effective_on')->latest('id')->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }
}
