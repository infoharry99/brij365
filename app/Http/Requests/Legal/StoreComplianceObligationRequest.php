<?php

namespace App\Http\Requests\Legal;

use App\Models\ComplianceObligation;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreComplianceObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ComplianceObligation::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'compliance_type' => ['required', 'string', 'max:120'],
            'due_on' => ['required', 'date'],
            'frequency' => ['required', 'string', Rule::in(['one_time', 'monthly', 'quarterly', 'half_yearly', 'annual'])],
            'priority' => ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $companyScope = app(CompanyScopeService::class);
                $companyId = $actor ? $companyScope->companyIdFor($actor) : 0;

                if ($this->filled('project_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if (! $project || ! $companyScope->allows($actor, $project->company_id) || $project->status !== 'active') {
                        $validator->errors()->add('project_id', 'The selected project is not active for your company.');
                    }
                } elseif ($companyId !== null && $companyId <= 0) {
                    $validator->errors()->add('project_id', 'Compliance obligations require a valid company scope.');
                }

                if ($this->filled('assigned_to_user_id')) {
                    $assignee = User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                    if (! $assignee || ! $companyScope->allows($actor, $assignee->company_id)) {
                        $validator->errors()->add('assigned_to_user_id', 'The selected assignee is not available for your company.');
                    }
                }
            },
        ];
    }
}
