<?php

namespace App\Http\Requests\Legal;

use App\Models\Project;
use App\Models\ReraRegistration;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReraRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ReraRegistration::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'registration_number' => ['required', 'string', 'max:80'],
            'authority_name' => ['required', 'string', 'max:160'],
            'state_code' => ['required', 'string', 'max:10'],
            'registered_on' => ['required', 'date'],
            'expires_on' => ['nullable', 'date', 'after:registered_on'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'conditions' => ['nullable', 'array', 'max:50'],
            'conditions.*' => ['string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $project = Project::query()->whereKey($this->integer('project_id'))->first();

                if (! $project || ! app(CompanyScopeService::class)->allows($user, $project->company_id) || $project->status !== 'active') {
                    $validator->errors()->add('project_id', 'The selected project is not active for your company.');
                }

                if (ReraRegistration::query()
                    ->where('company_id', $project?->company_id ?? 0)
                    ->where('registration_number', $this->string('registration_number')->toString())
                    ->exists()) {
                    $validator->errors()->add('registration_number', 'This RERA registration number already exists for your company.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('conditions')) {
            $conditions = collect((array) $this->input('conditions'))
                ->map(fn ($condition) => is_string($condition) ? trim($condition) : $condition)
                ->filter(fn ($condition) => $condition !== null && $condition !== '')
                ->values()
                ->all();

            $this->merge(['conditions' => $conditions]);
        }
    }
}
