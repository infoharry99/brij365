<?php

namespace App\Http\Requests\Hr;

use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PerformanceDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PerformanceReview::class) === true;
    }

    public function rules(): array
    {
        return [
            'cycle_id' => ['nullable', 'integer', Rule::exists('performance_cycles', 'id')->whereNull('deleted_at')],
            'department' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['cycle_id', 'department']);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('cycle_id')) {
                return;
            }

            $cycle = PerformanceCycle::find($this->integer('cycle_id'));
            if ($cycle && ! app(CompanyScopeService::class)->allows($this->user(), $cycle->company_id)) {
                $validator->errors()->add('cycle_id', 'The selected performance cycle is outside your company scope.');
            }
        }];
    }
}
