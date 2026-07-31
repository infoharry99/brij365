<?php

namespace App\Domain\Hr\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularizationRequest;
use App\Models\AuditEvent;
use App\Models\CommissionItem;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeLeaveBalance;
use App\Models\EmployeeLoan;
use App\Models\EmployeeMovement;
use App\Models\EmployeeProfileSection;
use App\Models\EmployeeSeparationSettlement;
use App\Models\EmployeeTaxDocument;
use App\Models\ExpenseClaim;
use App\Models\HrHelpdeskTicket;
use App\Models\LeaveEncashment;
use App\Models\LeaveRequest;
use App\Models\ManagedDocument;
use App\Models\PayrollRunItem;
use App\Models\PerformanceReview;
use App\Models\SalaryAssignment;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class EmployeeAuditRegister
{
    public function __construct(private readonly PaginationPolicy $pagination) {}

    public function events(Employee $employee, array $filters): LengthAwarePaginator
    {
        $targets = collect([
            ['type' => Employee::class, 'ids' => [$employee->id]],
            ...collect([EmployeeProfileSection::class, EmployeeMovement::class, AttendanceRecord::class, AttendanceRegularizationRequest::class, EmployeeLeaveBalance::class, LeaveRequest::class, LeaveEncashment::class, SalaryAssignment::class, PayrollRunItem::class, CommissionItem::class, EmployeeTaxDocument::class, EmployeeAsset::class, ExpenseClaim::class, EmployeeLoan::class, HrHelpdeskTicket::class, EmployeeConfirmationCase::class, EmployeeSeparationSettlement::class, EmployeeExitInterview::class, PerformanceReview::class])->map(fn (string $model) => ['type' => $model, 'ids' => $this->employeeIds($model, $employee)])->all(),
            ['type' => ManagedDocument::class, 'ids' => ManagedDocument::query()->where('owner_type', 'employee')->where('owner_id', $employee->id)->pluck('id')->map(fn ($id) => (int) $id)->all()],
        ])->filter(fn (array $target) => $target['ids'] !== [])->values();

        return AuditEvent::query()->with('user.role')->where(function (Builder $query) use ($targets): void {
            $targets->each(fn (array $target) => $query->orWhere(fn (Builder $targetQuery) => $targetQuery->where('auditable_type', $target['type'])->whereIn('auditable_id', $target['ids'])));
        })->when($filters['event_type'] ?? null, fn (Builder $query, string $eventType) => $query->where('event_type', $eventType))->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))->orderByDesc('created_at')->paginate($this->pagination->defaultPerPage($filters['per_page'] ?? null));
    }

    private function employeeIds(string $model, Employee $employee): array
    {
        return $model::query()->where('company_id', $employee->company_id)->where('employee_id', $employee->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
