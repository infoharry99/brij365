<?php

namespace App\Http\Requests\Hr;

use App\Models\PerformanceCycle;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PerformanceCycleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PerformanceCycle::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'closed', 'archived'])],
            'frequency' => ['nullable', 'string', Rule::in(['monthly', 'quarterly', 'annual'])],
            'department' => ['nullable', 'string', 'max:120'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'current' => ['nullable', 'boolean'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'status',
                'frequency',
                'department',
                'project_id',
                'current',
                'per_page',
                'page',
            ]);

            if ($validator->errors()->isNotEmpty() || ! $this->filled('project_id')) {
                return;
            }

            $project = Project::find($this->integer('project_id'));
            $user = $this->user();

            if ($project && (! $user || ! app(CompanyScopeService::class)->allows($user, $project->company_id))) {
                $validator->errors()->add('project_id', 'The selected project is outside your company scope.');
            }
        });
    }
}
