<?php

namespace App\Http\Requests\Construction;

use App\Models\ConstructionMilestone;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConstructionMilestoneIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ConstructionMilestone::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'status' => ['nullable', 'string', Rule::in(['planned', 'in_progress', 'completed', 'delayed'])],
            'phase' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
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
                    ['project_id', 'status', 'phase', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user || ! $this->filled('project_id') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $projectCompanyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                    $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                }
            },
        ];
    }
}
