<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveProcessingRun;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeaveProcessingRunIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeaveProcessingRun::class) === true;
    }

    public function rules(): array
    {
        return [
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'processing_type' => ['nullable', 'string', Rule::in(['monthly_accrual', 'year_end'])],
            'status' => ['nullable', 'string', Rule::in(['preview', 'posted', 'cancelled'])],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'period_year',
                'processing_type',
                'status',
                'per_page',
                'page',
            ]);
        });
    }
}
