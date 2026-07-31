<?php

namespace App\Http\Requests\Projects;

use App\Models\Branch;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProjectIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Project::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'status' => ['nullable', 'string', Rule::in(['planned', 'active', 'on_hold', 'completed', 'archived'])],
            'project_type' => ['nullable', 'string', Rule::in(['residential', 'commercial', 'villa', 'mixed_use', 'plotted', 'redevelopment'])],
            'search' => ['nullable', 'string', 'max:120'],
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
                    ['company_id', 'branch_id', 'status', 'project_type', 'search', 'per_page', 'page'],
                );

                $user = $this->user();
                if (! $user) {
                    return;
                }

                if ($this->filled('company_id') && ! app(CompanyScopeService::class)->allows($user, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'The selected company is not available for your access scope.');
                }

                if (! $this->filled('branch_id')) {
                    return;
                }

                $branch = Branch::query()->whereKey($this->integer('branch_id'))->first(['id', 'company_id']);
                if (! $branch || ! app(CompanyScopeService::class)->allows($user, $branch->company_id)) {
                    $validator->errors()->add('branch_id', 'The selected branch is not available for your access scope.');

                    return;
                }

                if ($this->filled('company_id') && (int) $branch->company_id !== $this->integer('company_id')) {
                    $validator->errors()->add('branch_id', 'The selected branch must belong to the selected company.');
                }
            },
        ];
    }
}
