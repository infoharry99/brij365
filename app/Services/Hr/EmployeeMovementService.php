<?php

namespace App\Services\Hr;

use App\Domain\Hr\Services\EmployeeHierarchyService;
use App\Models\Employee;
use App\Models\EmployeeMovement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeMovementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly EmployeeHierarchyService $hierarchy,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Employee $employee, array $data, User $actor, ?Request $request = null): EmployeeMovement
    {
        return DB::transaction(function () use ($employee, $data, $actor, $request): EmployeeMovement {
            $lockedEmployee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $newValues = $this->normalizedNewValues((string) $data['movement_type'], (array) $data['new_values']);
            $this->assertHierarchyChangeAllowed($lockedEmployee, $newValues);
            $previousValues = Arr::only($this->snapshot($lockedEmployee), array_keys($newValues));
            $status = (string) ($data['status'] ?? 'pending');

            $movement = EmployeeMovement::create([
                'company_id' => $lockedEmployee->company_id,
                'employee_id' => $lockedEmployee->id,
                'movement_number' => $this->nextMovementNumber(),
                'movement_type' => $data['movement_type'],
                'effective_on' => $data['effective_on'],
                'status' => $status,
                'previous_values' => $previousValues,
                'new_values' => $newValues,
                'reason' => $data['reason'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'workflow_history' => [[
                    'status' => 'created',
                    'actor_user_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'at' => now()->toISOString(),
                    'remarks' => $data['remarks'] ?? null,
                ]],
                'metadata' => [
                    'source' => 'employee_master',
                    'applied_fields' => array_keys($newValues),
                ],
                'created_by_user_id' => $actor->id,
            ]);

            if ($status === 'approved') {
                $movement = $this->applyMovement($movement, $lockedEmployee, $actor, $data['remarks'] ?? null);
            }

            $this->auditLogger->record(
                $actor,
                'hr.employee_movement.created',
                'Created employee movement record',
                $movement,
                [
                    'movement_number' => $movement->movement_number,
                    'employee_code' => $lockedEmployee->employee_code,
                    'movement_type' => $movement->movement_type,
                    'status' => $movement->status,
                    'effective_on' => $movement->effective_on?->toDateString(),
                    'changed_fields' => array_keys($newValues),
                ],
                $request,
            );

            $this->notifyEmployee($movement, $actor, 'Employee movement recorded', 'An HR movement has been recorded for '.$lockedEmployee->employee_code.'.');

            return $movement->load(['employee', 'company', 'createdBy', 'approvedBy']);
        });
    }

    public function approve(EmployeeMovement $movement, User $actor, ?string $remarks = null, ?Request $request = null): EmployeeMovement
    {
        return DB::transaction(function () use ($movement, $actor, $remarks, $request): EmployeeMovement {
            $lockedMovement = EmployeeMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            $lockedEmployee = Employee::query()->whereKey($lockedMovement->employee_id)->lockForUpdate()->firstOrFail();

            if ($lockedMovement->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending employee movements can be approved.']);
            }

            if ($lockedMovement->effective_on?->isFuture()) {
                throw ValidationException::withMessages(['effective_on' => 'Future-dated movements cannot be approved before their effective date.']);
            }

            $lockedMovement = $this->applyMovement($lockedMovement, $lockedEmployee, $actor, $remarks);

            $this->auditLogger->record(
                $actor,
                'hr.employee_movement.approved',
                'Approved and applied employee movement',
                $lockedMovement,
                [
                    'movement_number' => $lockedMovement->movement_number,
                    'employee_code' => $lockedEmployee->employee_code,
                    'movement_type' => $lockedMovement->movement_type,
                    'effective_on' => $lockedMovement->effective_on?->toDateString(),
                    'changed_fields' => array_keys($lockedMovement->new_values ?? []),
                ],
                $request,
            );

            $this->notifyEmployee($lockedMovement, $actor, 'Employee movement approved', 'An approved HR movement has been applied to your employee master record.');

            return $lockedMovement->load(['employee', 'company', 'createdBy', 'approvedBy']);
        });
    }

    private function applyMovement(EmployeeMovement $movement, Employee $employee, User $actor, ?string $remarks = null): EmployeeMovement
    {
        $newValues = $movement->new_values ?? [];
        $this->assertHierarchyChangeAllowed($employee, $newValues);
        $employee->forceFill($newValues)->save();

        $history = $movement->workflow_history ?? [];
        $history[] = [
            'status' => 'approved',
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'at' => now()->toISOString(),
            'remarks' => $remarks,
        ];

        $movement->forceFill([
            'status' => 'approved',
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
            'remarks' => $remarks ?? $movement->remarks,
            'workflow_history' => $history,
        ])->save();

        return $movement->refresh();
    }

    /**
     * @param array<string, mixed> $newValues
     */
    private function assertHierarchyChangeAllowed(Employee $employee, array $newValues): void
    {
        if (! array_key_exists('manager_employee_id', $newValues) || $newValues['manager_employee_id'] === null) {
            return;
        }

        $managerId = (int) $newValues['manager_employee_id'];
        $managerExistsInCompany = Employee::query()
            ->whereKey($managerId)
            ->where('company_id', $employee->company_id)
            ->exists();

        if (! $managerExistsInCompany) {
            throw ValidationException::withMessages([
                'new_values.manager_employee_id' => 'The reporting manager must belong to the same company.',
            ]);
        }

        if ($this->hierarchy->wouldCreateCycle($employee, $managerId)) {
            throw ValidationException::withMessages([
                'new_values.manager_employee_id' => 'The reporting relationship would create a management cycle.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function normalizedNewValues(string $movementType, array $values): array
    {
        $allowed = match ($movementType) {
            'transfer' => ['branch_id', 'project_id', 'department'],
            'promotion' => ['designation', 'grade', 'monthly_ctc'],
            'department_change' => ['department'],
            'reporting_change' => ['manager_employee_id'],
            'salary_change' => ['monthly_ctc'],
            'status_change' => ['status'],
            'grade_change' => ['grade'],
            default => [],
        };

        return collect(Arr::only($values, $allowed))
            ->filter(fn ($value): bool => $value !== '')
            ->map(function ($value, string $key) {
                if (in_array($key, ['branch_id', 'project_id', 'manager_employee_id'], true) && $value !== null) {
                    return (int) $value;
                }

                if ($key === 'monthly_ctc' && $value !== null) {
                    return number_format((float) $value, 2, '.', '');
                }

                return $value;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Employee $employee): array
    {
        return [
            'branch_id' => $employee->branch_id,
            'project_id' => $employee->project_id,
            'manager_employee_id' => $employee->manager_employee_id,
            'designation' => $employee->designation,
            'department' => $employee->department,
            'grade' => $employee->grade,
            'status' => $employee->status,
            'monthly_ctc' => $employee->monthly_ctc === null ? null : number_format((float) $employee->monthly_ctc, 2, '.', ''),
        ];
    }

    private function nextMovementNumber(): string
    {
        return sprintf('HRMOV-%05d', EmployeeMovement::query()->withTrashed()->count() + 10001);
    }

    private function notifyEmployee(EmployeeMovement $movement, User $actor, string $title, string $body): void
    {
        $employee = $movement->employee;

        if (! $employee?->user) {
            return;
        }

        $this->notifications->sendToUser($employee->user, [
            'category' => 'hr',
            'severity' => $movement->status === 'approved' ? 'success' : 'info',
            'title' => $title,
            'body' => $body,
            'action_url' => '/hr/employees/'.$employee->id,
            'payload' => [
                'movement_number' => $movement->movement_number,
                'movement_type' => $movement->movement_type,
                'status' => $movement->status,
            ],
        ], $actor, $movement);
    }
}
