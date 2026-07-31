<?php

namespace App\Services\Hr;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeProfileService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ActiveInternalUserEligibility $internalUsers,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?Request $request = null): Employee
    {
        return DB::transaction(function () use ($data, $actor, $request): Employee {
            $this->assertLinkableUser($actor, $data['user_id'] ?? null, $data['company_id']);

            $employee = Employee::create([
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'manager_employee_id' => $data['manager_employee_id'] ?? null,
                'employee_code' => $data['employee_code'],
                'name' => $data['name'],
                'designation' => $data['designation'],
                'department' => $data['department'],
                'grade' => $data['grade'] ?? null,
                'employment_type' => $data['employment_type'],
                'status' => $data['status'] ?? 'active',
                'joined_on' => $data['joined_on'] ?? null,
                'statutory_state' => $data['statutory_state'] ?? null,
                'monthly_ctc' => $data['monthly_ctc'] ?? null,
                'sensitive_profile' => $data['sensitive_profile'] ?? [],
            ]);

            $this->auditLogger->record(
                $actor,
                'hr.employee.created',
                'Created employee master record',
                $employee,
                [
                    'employee_code' => $employee->employee_code,
                    'department' => $employee->department,
                    'designation' => $employee->designation,
                    'status' => $employee->status,
                ],
                $request,
            );

            return $employee->load($this->relations())->loadCount(['directReports', 'managedDocuments']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Employee $employee, array $data, User $actor, ?Request $request = null): Employee
    {
        return DB::transaction(function () use ($employee, $data, $actor, $request): Employee {
            $lockedEmployee = Employee::query()->whereKey($employee->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('lock_version', $data)
                && (int) $data['lock_version'] !== (int) $lockedEmployee->lock_version) {
                throw ValidationException::withMessages([
                    'lock_version' => 'This employee record was updated by another user. Refresh the profile before saving your changes.',
                ]);
            }

            $before = $this->snapshot($lockedEmployee);

            if (array_key_exists('user_id', $data)) {
                $this->assertLinkableUser($actor, $data['user_id'], $lockedEmployee->company_id, $lockedEmployee->id);
            }

            $lockedEmployee->forceFill(array_intersect_key($data, array_flip([
                'branch_id',
                'project_id',
                'user_id',
                'manager_employee_id',
                'employee_code',
                'name',
                'designation',
                'department',
                'grade',
                'employment_type',
                'status',
                'joined_on',
                'statutory_state',
                'monthly_ctc',
                'sensitive_profile',
            ])));
            $lockedEmployee->lock_version = ((int) $lockedEmployee->lock_version) + 1;
            $lockedEmployee->save();

            $after = $this->snapshot($lockedEmployee);

            $this->auditLogger->record(
                $actor,
                'hr.employee.updated',
                'Updated employee master record',
                $lockedEmployee,
                [
                    'employee_code' => $lockedEmployee->employee_code,
                    'before' => $before,
                    'after' => $after,
                ],
                $request,
            );

            return $lockedEmployee->load($this->relations())->loadCount(['directReports', 'managedDocuments']);
        });
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['company', 'branch', 'project', 'user.role', 'manager'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Employee $employee): array
    {
        return [
            'branch_id' => $employee->branch_id,
            'project_id' => $employee->project_id,
            'user_id' => $employee->user_id,
            'manager_employee_id' => $employee->manager_employee_id,
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'designation' => $employee->designation,
            'department' => $employee->department,
            'grade' => $employee->grade,
            'employment_type' => $employee->employment_type,
            'status' => $employee->status,
            'joined_on' => $employee->joined_on?->toDateString(),
            'statutory_state' => $employee->statutory_state,
            'monthly_ctc' => $employee->monthly_ctc === null ? null : (float) $employee->monthly_ctc,
            'sensitive_profile_keys' => array_keys($employee->sensitive_profile ?? []),
        ];
    }

    private function assertLinkableUser(
        User $actor,
        mixed $userId,
        int|string|null $companyId,
        ?int $excludedEmployeeId = null,
    ): void {
        if ($userId === null || $userId === '') {
            return;
        }

        $candidate = User::query()->with('role')->find((int) $userId);

        if (! $candidate) {
            throw ValidationException::withMessages(['user_id' => 'The selected user is invalid.']);
        }

        $this->internalUsers->assertEligible(
            $actor,
            $candidate,
            $companyId,
            'user_id',
            'The linked user must be an active internal user in the employee company.',
        );

        if (Employee::query()
            ->where('user_id', $candidate->id)
            ->when($excludedEmployeeId, fn ($query) => $query->whereKeyNot($excludedEmployeeId))
            ->exists()) {
            throw ValidationException::withMessages([
                'user_id' => $excludedEmployeeId
                    ? 'The selected user is already linked to another employee profile.'
                    : 'The selected user is already linked to an employee profile.',
            ]);
        }
    }
}
