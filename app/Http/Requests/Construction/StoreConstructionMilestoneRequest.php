<?php

namespace App\Http\Requests\Construction;

use App\Models\ConstructionMilestone;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConstructionMilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ConstructionMilestone::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'milestone_code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phase' => ['required', 'string', 'max:120'],
            'planned_start_on' => ['required', 'date'],
            'planned_end_on' => ['required', 'date', 'after_or_equal:planned_start_on'],
            'weight_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'dependencies' => ['nullable', 'array', 'max:20'],
            'dependencies.*' => ['string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = Project::query()->whereKey($this->integer('project_id'))->first();
                $user = $this->user();

                if (
                    ! $project
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $project->company_id)
                    || $project->status !== 'active'
                ) {
                    $validator->errors()->add('project_id', 'The selected project is not active for your company.');
                }

                if (ConstructionMilestone::query()
                    ->where('project_id', $this->integer('project_id'))
                    ->where('milestone_code', $this->string('milestone_code')->toString())
                    ->exists()) {
                    $validator->errors()->add('milestone_code', 'This milestone code already exists for the selected project.');
                }
            },
        ];
    }
}
