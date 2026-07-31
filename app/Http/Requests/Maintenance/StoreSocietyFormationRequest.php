<?php

namespace App\Http\Requests\Maintenance;

use App\Models\Project;
use App\Models\SocietyFormation;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSocietyFormationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SocietyFormation::class) === true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'society_name' => ['required', 'string', 'max:255'],
            'association_type' => ['nullable', 'string', Rule::in(['cooperative_society', 'apartment_association', 'commercial_association'])],
            'total_units' => ['required', 'integer', 'min:1', 'max:10000'],
            'occupied_units' => ['nullable', 'integer', 'min:0', 'lte:total_units'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'application_filed_on' => ['nullable', 'date', 'before_or_equal:today'],
            'registered_on' => ['nullable', 'date', 'before_or_equal:today'],
            'target_handover_on' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'application_filed', 'in_progress', 'formed', 'handed_over', 'blocked'])],
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'current_stage' => ['nullable', 'string', 'max:120'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'committee_members' => ['nullable', 'array', 'max:50'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $project = Project::query()->whereKey($this->integer('project_id'))->first();
                $user = $this->user();

                if (! $project || ! $user || ! app(CompanyScopeService::class)->allows($user, $project->company_id)) {
                    $validator->errors()->add('project_id', 'The selected project is outside your company scope.');

                    return;
                }

                $exists = SocietyFormation::query()
                    ->where('project_id', $project->id)
                    ->where('society_name', $this->input('society_name'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('society_name', 'A society formation record already exists for this project and name.');
                }
            },
        ];
    }
}
