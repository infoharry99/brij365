<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Models\ManagedDocument;
use Illuminate\Foundation\Http\FormRequest;

class ApproveEmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');
        $managedDocument = $this->route('managedDocument');

        return $employee instanceof Employee
            && $managedDocument instanceof ManagedDocument
            && $managedDocument->owner_type === 'employee'
            && (int) $managedDocument->owner_id === (int) $employee->id
            && ($this->user()?->can('update', $employee) ?? false)
            && ($this->user()?->can('approve', $managedDocument) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
