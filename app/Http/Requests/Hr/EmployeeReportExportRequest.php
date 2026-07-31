<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeReportExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && $actor->can('viewAny', Employee::class)
            && app(EmployeeFieldVisibility::class)->canExportRegister($actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', Rule::in(['csv', 'excel', 'xls', 'pdf'])],
            'report_type' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'department' => ['nullable', 'string', 'max:120'],
            'designation' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'on_notice', 'separated'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'format',
                'report_type',
                'company_id',
                'branch_id',
                'project_id',
                'department',
                'designation',
                'status',
                'search',
            ]);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $companyScope = app(CompanyScopeService::class);

            if (! $user) {
                return;
            }

            if ($this->filled('company_id') && ! $companyScope->allows($user, $this->integer('company_id'))) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            if ($this->filled('branch_id')) {
                $branch = Branch::find($this->integer('branch_id'));

                if ($branch && ! $companyScope->allows($user, $branch->company_id)) {
                    $validator->errors()->add('branch_id', 'The selected branch is outside your company scope.');
                }
            }

            if ($this->filled('project_id')) {
                $project = Project::find($this->integer('project_id'));

                if ($project && ! $companyScope->allows($user, $project->company_id)) {
                    $validator->errors()->add('project_id', 'The selected project is outside your company scope.');
                }
            }
        });
    }
}
