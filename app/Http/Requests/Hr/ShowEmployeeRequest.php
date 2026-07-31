<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class ShowEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee
            && $this->user()?->can('view', $employee) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
