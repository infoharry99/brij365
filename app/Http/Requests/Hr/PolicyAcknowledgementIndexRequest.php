<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeePolicyAcknowledgement;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PolicyAcknowledgementIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeePolicyAcknowledgement::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'policy_key' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['pending', 'acknowledged'])],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                    'employee_id',
                    'policy_key',
                    'status',
                    'per_page',
                    'page',
                ]);
            },
        ];
    }
}
