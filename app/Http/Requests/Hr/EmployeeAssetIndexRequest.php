<?php

namespace App\Http\Requests\Hr;

use App\Http\Requests\Concerns\ValidatesEmployeeFilterScope;
use App\Models\EmployeeAsset;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeAssetIndexRequest extends FormRequest
{
    use ValidatesEmployeeFilterScope;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EmployeeAsset::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'category' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(['available', 'assigned', 'recovered', 'retired', 'lost'])],
            'search' => ['nullable', 'string', 'max:120'],
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
                    'category',
                    'status',
                    'search',
                    'per_page',
                    'page',
                ]);

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateEmployeeFilterScope($validator, ['assets.view', 'assets.manage', 'hr.manage']);
            },
        ];
    }
}
