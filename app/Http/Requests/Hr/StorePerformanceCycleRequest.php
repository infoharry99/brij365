<?php

namespace App\Http\Requests\Hr;

use App\Models\PerformanceCycle;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePerformanceCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PerformanceCycle::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'annual'])],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active'])],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'review_due_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'department' => ['nullable', 'string', 'max:120'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'rating_scale_min' => ['nullable', 'integer', 'min:1', 'max:9'],
            'rating_scale_max' => ['nullable', 'integer', 'min:2', 'max:10'],
            'passing_score' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'rules' => ['nullable', 'array'],
            'rules.kpi_weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rules.kra_weight_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rules.pip_threshold' => ['nullable', 'numeric', 'min:1', 'max:10'],
        ];
    }

    public function after(): array
    {
        return [$this->validateProjectAndScale(...)];
    }

    protected function validateProjectAndScale(Validator $validator): void
    {
        $actor = $this->user();
        $companyScope = app(CompanyScopeService::class);
        $project = null;

        if ($actor && $companyScope->companyIdFor($actor) === 0) {
            $validator->errors()->add('company_id', 'A company assignment is required to create performance cycles.');
        }

        if ($this->filled('project_id')) {
            $project = Project::find($this->integer('project_id'));

            if ($project && (! $actor || ! $companyScope->allows($actor, $project->company_id))) {
                $validator->errors()->add('project_id', 'The selected project does not belong to your company.');
            }
        }

        if ($this->filled('company_id')) {
            $companyId = $this->integer('company_id');

            if (! $actor || ! $companyScope->allows($actor, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            if ($project && (int) $project->company_id !== $companyId) {
                $validator->errors()->add('company_id', 'The selected company must match the selected project company.');
            }
        }

        if ($actor && $companyScope->hasUnrestrictedCompanyScope($actor) && ! $this->filled('company_id') && ! $project) {
            $validator->errors()->add('company_id', 'A company is required when creating a company-level performance cycle as a global user.');
        }

        $min = $this->integer('rating_scale_min') ?: 1;
        $max = $this->integer('rating_scale_max') ?: 5;
        $passing = (float) ($this->input('passing_score', 3));

        if ($min >= $max) {
            $validator->errors()->add('rating_scale_max', 'The rating scale maximum must be greater than the minimum.');
        }

        if ($passing < $min || $passing > $max) {
            $validator->errors()->add('passing_score', 'The passing score must fall within the configured rating scale.');
        }
    }
}
