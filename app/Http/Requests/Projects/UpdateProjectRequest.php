<?php

namespace App\Http\Requests\Projects;

use App\Models\Branch;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('status', 'active')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('status', 'active')],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('projects', 'code')->ignore($project->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'project_type' => ['required', 'string', Rule::in(['residential', 'commercial', 'villa', 'mixed_use', 'plotted', 'redevelopment'])],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'status' => ['required', 'string', Rule::in(['planned', 'active', 'on_hold', 'completed', 'archived'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999999999.99'],
            'target_roi_percent' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $companyId = $this->integer('company_id');

                if (! $user || ! app(CompanyScopeService::class)->allows($user, $companyId)) {
                    $validator->errors()->add('company_id', 'The selected company is not available for your access scope.');
                }

                if ($this->filled('branch_id')) {
                    $branch = Branch::query()
                        ->whereKey($this->integer('branch_id'))
                        ->first(['id', 'company_id']);

                    if (! $branch || (int) $branch->company_id !== $companyId) {
                        $validator->errors()->add('branch_id', 'The selected branch must belong to the selected company.');
                    }
                }
            },
        ];
    }
}
