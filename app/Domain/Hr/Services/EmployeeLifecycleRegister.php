<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\LifecycleSummaryData;
use App\Application\Hr\Data\LifecycleTrackerRowData;
use App\Models\Employee;
use App\Models\EmployeeConfirmationCase;
use App\Models\EmployeeExitInterview;
use App\Models\EmployeeSeparationSettlement;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EmployeeLifecycleRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @return LengthAwarePaginator<int, LifecycleTrackerRowData> */
    public function events(User $actor, array $filters): LengthAwarePaginator
    {
        $query = $this->filteredEventsQuery($actor, $filters);
        $perPage = $this->pagination->defaultPerPage(isset($filters['per_page']) ? (int) $filters['per_page'] : null);
        $paginator = $query
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(fn (object $row): LifecycleTrackerRowData => $this->present($row)));

        return $paginator;
    }

    public function summary(User $actor, array $filters): LifecycleSummaryData
    {
        $base = $this->filteredEventsQuery($actor, array_diff_key($filters, ['page' => true, 'per_page' => true]));

        return new LifecycleSummaryData(
            totalEvents: (clone $base)->count(),
            pendingMovements: (clone $base)->where('event_type', 'movement')->where('status', 'pending')->count(),
            openConfirmations: (clone $base)->where('event_type', 'confirmation')->whereNotIn('status', ['confirmed', 'rejected'])->count(),
            openSeparations: (clone $base)->where('event_type', 'separation')->where('status', '!=', 'completed')->count(),
            openExitInterviews: (clone $base)->where('event_type', 'exit')->where('status', '!=', 'reviewed')->count(),
        );
    }

    /** @return Collection<int, array{id: int, label: string}> */
    public function employees(User $actor): Collection
    {
        $source = $this->baseEventsQuery($actor)->select('employee_id')->distinct();

        return Employee::query()
            ->whereIn('id', $source)
            ->orderBy('name')
            ->get(['id', 'employee_code', 'name'])
            ->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'label' => $employee->name.' · '.$employee->employee_code,
            ]);
    }

    /** @return Collection<int, string> */
    public function departments(User $actor): Collection
    {
        return $this->baseEventsQuery($actor)
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');
    }

    private function filteredEventsQuery(User $actor, array $filters): Builder
    {
        $query = $this->baseEventsQuery($actor);
        $stage = (string) ($filters['stage'] ?? 'all');

        if ($stage !== '' && $stage !== 'all') {
            $query->where('event_type', match ($stage) {
                'movements' => 'movement',
                'confirmation' => 'confirmation',
                'separation' => 'separation',
                'exit' => 'exit',
                default => $stage,
            });
        }

        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', (int) $filters['employee_id']);
        }

        if (! empty($filters['department'])) {
            $query->where('department', (string) $filters['department']);
        }

        return $query;
    }

    private function baseEventsQuery(User $actor): Builder
    {
        $queries = [];

        if ($this->canReadMovements($actor)) {
            $queries[] = $this->movementQuery($actor);
        }

        if ($actor->can('viewAny', EmployeeConfirmationCase::class)) {
            $queries[] = $this->confirmationQuery($actor);
        }

        if ($actor->can('viewAny', EmployeeSeparationSettlement::class)) {
            $queries[] = $this->separationQuery($actor);
        }

        if ($actor->can('viewAny', EmployeeExitInterview::class)) {
            $queries[] = $this->exitQuery($actor);
        }

        abort_if($queries === [], 403);

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'lifecycle_events');
    }

    private function movementQuery(User $actor): Builder
    {
        $query = $this->eventQuery('employee_movements', 'em', [
            'movement',
            'em.movement_number',
            'em.status',
            'em.effective_on',
        ]);

        return $this->scopeEmployeeRows($query, $actor, 'em.company_id', false);
    }

    private function confirmationQuery(User $actor): Builder
    {
        $query = $this->eventQuery('employee_confirmation_cases', 'ec', [
            'confirmation',
            'ec.case_number',
            'ec.status',
            'ec.review_due_on',
        ]);
        $query = $this->scopeEmployeeRows($query, $actor, 'ec.company_id', true);

        if (! $this->hasHrRead($actor) && $actor->hasPermission('performance.manage')) {
            $employeeId = $actor->employee?->id;
            $query->where(function (Builder $nested) use ($actor, $employeeId): void {
                $nested->where('e.user_id', $actor->id);
                if ($employeeId !== null) {
                    $nested->orWhere('ec.manager_employee_id', $employeeId);
                }
            });
        }

        return $query;
    }

    private function separationQuery(User $actor): Builder
    {
        $query = $this->eventQuery('employee_separation_settlements', 'es', [
            'separation',
            'es.settlement_number',
            'es.status',
            'es.last_working_date',
        ]);

        return $this->scopeEmployeeRows($query, $actor, 'es.company_id', true);
    }

    private function exitQuery(User $actor): Builder
    {
        $query = $this->eventQuery('employee_exit_interviews', 'ei', [
            'exit',
            'ei.interview_number',
            'ei.status',
            'ei.interview_due_on',
        ]);

        return $this->scopeEmployeeRows($query, $actor, 'ei.company_id', true);
    }

    /**
     * @param array{0: string, 1: string, 2: string, 3: string} $event
     */
    private function eventQuery(string $table, string $alias, array $event): Builder
    {
        return DB::table($table.' as '.$alias)
            ->join('employees as e', 'e.id', '=', $alias.'.employee_id')
            ->whereNull($alias.'.deleted_at')
            ->whereNull('e.deleted_at')
            ->select([
                $alias.'.id as id',
                'e.id as employee_id',
                'e.employee_code',
                'e.name as employee_name',
                'e.department',
                'e.designation',
                'e.status as employee_status',
            ])
            ->selectRaw('? as event_type', [$event[0]])
            ->addSelect(DB::raw($event[1].' as number'))
            ->addSelect(DB::raw($event[2].' as status'))
            ->addSelect(DB::raw($event[3].' as event_date'));
    }

    private function scopeEmployeeRows(Builder $query, User $actor, string $companyColumn, bool $allowModuleWide): Builder
    {
        $companyId = $this->scope->companyIdFor($actor);
        if ($companyId !== null) {
            $query->where($companyColumn, $companyId);
        }

        if ($allowModuleWide && ($this->hasHrRead($actor) || $actor->hasPermission('finance.view') || $actor->hasPermission('finance.approve'))) {
            return $query;
        }

        return $query->where('e.user_id', $actor->id);
    }

    private function canReadMovements(User $actor): bool
    {
        return $this->hasHrRead($actor) || $actor->hasPermission('employee.self_service');
    }

    private function hasHrRead(User $actor): bool
    {
        return $actor->hasPermission('*') || $actor->hasPermission('hr.view') || $actor->hasPermission('hr.manage');
    }

    private function present(object $row): LifecycleTrackerRowData
    {
        $eventType = (string) $row->event_type;
        $status = (string) $row->status;
        $date = (string) $row->event_date;

        return new LifecycleTrackerRowData(
            id: (int) $row->id,
            employeeId: (int) $row->employee_id,
            employeeCode: (string) $row->employee_code,
            employeeName: (string) $row->employee_name,
            department: (string) ($row->department ?: 'Not assigned'),
            designation: (string) ($row->designation ?: 'Not assigned'),
            employeeStatus: (string) $row->employee_status,
            eventType: $eventType,
            eventTypeLabel: match ($eventType) {
                'movement' => 'Movement',
                'confirmation' => 'Confirmation',
                'separation' => 'Full & Final',
                'exit' => 'Exit Interview',
                default => Str::headline($eventType),
            },
            number: (string) $row->number,
            status: $status,
            statusLabel: Str::headline($status),
            statusTone: $this->statusTone($status),
            eventDate: $date,
            eventDateLabel: $date !== '' ? date('d M Y', strtotime($date)) : 'Not scheduled',
            url: match ($eventType) {
                'movement' => route('hr.employees.movements.index', $row->employee_id),
                'confirmation' => route('hr.confirmation-cases.index', ['employee_id' => $row->employee_id]),
                'separation' => route('hr.separation-settlements.index', ['employee_id' => $row->employee_id]),
                'exit' => route('hr.exit-interviews.index', ['employee_id' => $row->employee_id]),
                default => route('hr.employees.show', $row->employee_id),
            },
        );
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'approved', 'confirmed', 'completed', 'reviewed' => 'success',
            'rejected', 'cancelled' => 'danger',
            'pending', 'due', 'scheduled', 'submitted', 'manager_recommended', 'hr_approved', 'finance_approved' => 'warning',
            default => 'info',
        };
    }
}
