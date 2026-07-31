<?php

namespace App\Application\Hr\Data;

use App\Models\Employee;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class EmployeeAuditPageData
{
    public function __construct(public Employee $employee, public LengthAwarePaginator $events, public array $profileNavigation) {}

    public function toView(): array { return get_object_vars($this); }
}
