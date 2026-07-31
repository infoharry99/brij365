<?php

namespace App\Http\Requests\Hr;

use App\Models\Employee;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EmployeeAuditEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $employee = $this->route('employee');

        if (! $user || ! $employee instanceof Employee) {
            return false;
        }

        if (! $user->can('view', $employee)) {
            return false;
        }

        return $user->hasPermission('*')
            || $user->hasPermission('audit.view')
            || $user->hasPermission('hr.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['event_type', 'date_from', 'date_to', 'per_page', 'page'],
                );
            },
        ];
    }
}
