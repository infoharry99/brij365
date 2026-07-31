<?php

namespace App\Http\Requests\Payroll;

use App\Models\CommissionRule;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CommissionRuleIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CommissionRule::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'inactive'])],
            'rule_type' => ['nullable', 'string', Rule::in(['fixed', 'percentage', 'slab', 'target'])],
            'basis' => ['nullable', 'string', Rule::in(['booking_value', 'collection_received'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'search' => ['nullable', 'string', 'max:120'],
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
                ['status', 'rule_type', 'basis', 'project_id', 'search', 'per_page', 'page'],
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
