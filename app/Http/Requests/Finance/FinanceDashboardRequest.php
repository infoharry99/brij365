<?php

namespace App\Http\Requests\Finance;

use App\Models\Company;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinanceDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasPermission('finance.view') === true
            || $user?->hasPermission('finance.manage') === true
            || $user?->hasPermission('finance.approve') === true
            || $user?->hasPermission('collections.view') === true
            || $user?->hasPermission('collections.manage') === true
            || $user?->hasPermission('collections.approve') === true
            || $user?->hasPermission('reports.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'forecast_days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['company_id', 'project_id', 'date_from', 'date_to', 'forecast_days'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                    return;
                }

                $this->validateCompanyScope($validator);
                $this->validateProjectScope($validator);
            },
        ];
    }

    private function validateCompanyScope(Validator $validator): void
    {
        if (! $this->filled('company_id')) {
            return;
        }

        $companyId = Company::query()->whereKey($this->integer('company_id'))->value('id');

        if (! app(CompanyScopeService::class)->allows($this->user(), $companyId)) {
            $validator->errors()->add('company_id', 'The selected company is not available for your access scope.');
        }
    }

    private function validateProjectScope(Validator $validator): void
    {
        if (! $this->filled('project_id')) {
            return;
        }

        $project = Project::query()
            ->whereKey($this->integer('project_id'))
            ->first(['id', 'company_id']);

        if (! $project) {
            return;
        }

        if (! app(CompanyScopeService::class)->allows($this->user(), $project->company_id)) {
            $validator->errors()->add('project_id', 'The selected project is not available for your company.');

            return;
        }

        if ($this->filled('company_id') && (int) $this->integer('company_id') !== (int) $project->company_id) {
            $validator->errors()->add('project_id', 'The selected project does not belong to the selected company.');
        }
    }
}
