<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalesAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Lead::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['project_id', 'date_from', 'date_to']);

            if (! $this->user() || ! $this->filled('project_id')) {
                return;
            }

            $companyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');
            if (! app(CompanyScopeService::class)->allows($this->user(), $companyId)) {
                $validator->errors()->add('project_id', 'The selected project is not available for your company.');
            }
        }];
    }
}
