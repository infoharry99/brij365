<?php

namespace App\Http\Requests\Possession;

use App\Models\PossessionHandover;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PossessionHandoverIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PossessionHandover::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'status' => ['nullable', Rule::in(['ready', 'blocked', 'completed'])],
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
                ['project_id', 'status', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('project_id')) {
                return;
            }

            $project = Project::find($this->integer('project_id'));
            $user = $this->user();

            if ($project && $user && ! app(CompanyScopeService::class)->allows($user, $project->company_id)) {
                $validator->errors()->add('project_id', 'The selected project is outside your company scope.');
            }
        });
    }
}
