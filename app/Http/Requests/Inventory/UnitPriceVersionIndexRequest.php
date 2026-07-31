<?php

namespace App\Http\Requests\Inventory;

use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\UnitPriceVersion;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UnitPriceVersionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UnitPriceVersion::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'project_unit_id' => ['nullable', 'integer', Rule::exists('project_units', 'id')],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'archived'])],
            'effective_on' => ['nullable', 'date'],
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
                    ['project_id', 'project_unit_id', 'status', 'effective_on', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $companyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('project_unit_id')) {
                    $companyId = ProjectUnit::query()->whereKey($this->integer('project_unit_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                        $validator->errors()->add('project_unit_id', 'The selected unit is not available for your company.');
                    }
                }
            },
        ];
    }
}
