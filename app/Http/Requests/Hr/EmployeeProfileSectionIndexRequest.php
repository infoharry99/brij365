<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeProfileSectionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');
        $actor = $this->user();

        return $employee
            && $actor
            && $actor->can('view', $employee)
            && app(EmployeeFieldVisibility::class)->canViewSensitiveProfile($actor, $employee);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
