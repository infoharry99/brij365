<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Documents\StoreManagedDocumentRequest;
use App\Models\Employee;

class StoreEmployeeDocumentRequest extends StoreManagedDocumentRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && ($this->user()?->can('update', $employee) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $employee = $this->route('employee');

        if ($employee instanceof Employee) {
            $this->merge([
                'owner_type' => 'employee',
                'owner_id' => $employee->id,
            ]);
        }
    }
}
