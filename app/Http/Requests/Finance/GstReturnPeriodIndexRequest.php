<?php

namespace App\Http\Requests\Finance;

use App\Models\GstReturnPeriod;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GstReturnPeriodIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GstReturnPeriod::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['prepared', 'approved', 'locked'])],
            'period_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
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
                    ['status', 'period_year', 'period_month', 'per_page', 'page'],
                );
            },
        ];
    }
}
