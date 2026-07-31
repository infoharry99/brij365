<?php

namespace App\Http\Requests\Finance;

use App\Models\GstEntry;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GstEntryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GstEntry::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'locked'])],
            'transaction_type' => ['nullable', 'string', Rule::in(['output', 'input', 'reverse_charge', 'adjustment'])],
            'period_year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['status', 'transaction_type', 'period_year', 'period_month', 'project_id', 'q', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('project_id')) {
                return;
            }

            $projectCompanyId = Project::query()
                ->whereKey($this->integer('project_id'))
                ->value('company_id');

            if (! app(CompanyScopeService::class)->allows($this->user(), $projectCompanyId)) {
                $validator->errors()->add('project_id', 'The selected project is outside your company scope.');
            }
        });
    }
}
