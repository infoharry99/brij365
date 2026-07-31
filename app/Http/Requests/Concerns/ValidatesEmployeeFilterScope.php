<?php

namespace App\Http\Requests\Concerns;

use App\Models\Employee;
use App\Services\Security\CompanyScopeService;
use Illuminate\Validation\Validator;

trait ValidatesEmployeeFilterScope
{
    /**
     * @param array<int, string> $privilegedPermissions
     */
    protected function validateEmployeeFilterScope(Validator $validator, array $privilegedPermissions, string $field = 'employee_id'): void
    {
        if ($validator->errors()->isNotEmpty() || ! $this->filled($field)) {
            return;
        }

        $user = $this->user();
        $employee = Employee::query()->find($this->integer($field));

        if (! $user || ! $employee) {
            return;
        }

        if (! app(CompanyScopeService::class)->allows($user, $employee->company_id)) {
            $validator->errors()->add($field, 'The selected employee is not available for your company.');

            return;
        }

        if (! $this->hasAnyPermission($privilegedPermissions) && $employee->user_id !== $user->id) {
            $validator->errors()->add($field, 'The selected employee is outside your employee self-service scope.');
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function hasAnyPermission(array $permissions): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
