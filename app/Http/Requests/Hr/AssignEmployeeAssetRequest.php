<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('employeeAsset');

        return $asset instanceof \App\Models\EmployeeAsset
            && $this->user()?->can('assign', $asset) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'assigned_on' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $asset = $this->route('employeeAsset');
                $employee = \App\Models\Employee::find($this->integer('employee_id'));

                if ($asset instanceof \App\Models\EmployeeAsset && $employee && $asset->company_id !== $employee->company_id) {
                    $validator->errors()->add('employee_id', 'The employee must belong to the same company as the asset.');
                }
            },
        ];
    }
}
