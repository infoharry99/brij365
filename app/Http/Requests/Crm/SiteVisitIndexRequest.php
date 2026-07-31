<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SiteVisitIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SiteVisit::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string', Rule::in(['scheduled', 'completed', 'cancelled', 'no_show'])],
            'visit_mode' => ['nullable', 'string', Rule::in(['site', 'office', 'virtual'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
                    ['lead_id', 'project_id', 'assigned_to_user_id', 'status', 'visit_mode', 'date_from', 'date_to', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                    return;
                }

                if ($this->filled('lead_id')) {
                    $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();

                    if ($lead && ! app(CompanyScopeService::class)->allows($this->user(), $lead->company_id)) {
                        $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');
                    }
                }

                if ($this->filled('project_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if ($project && ! app(CompanyScopeService::class)->allows($this->user(), $project->company_id)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('assigned_to_user_id')) {
                    $assignee = User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                    if ($assignee && ! app(CompanyScopeService::class)->allows($this->user(), $assignee->company_id)) {
                        $validator->errors()->add('assigned_to_user_id', 'The selected assignee is not available for your company.');
                    }
                }
            },
        ];
    }
}
