<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\ExpenseClaim;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseClaimIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ExpenseClaim::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'claim_type' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'rejected', 'paid'])],
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
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                    'employee_id',
                    'claim_type',
                    'status',
                    'date_from',
                    'date_to',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['claims.view', 'claims.manage', 'claims.approve', 'finance.approve', 'hr.manage']);
            },
        ];
    }
}
