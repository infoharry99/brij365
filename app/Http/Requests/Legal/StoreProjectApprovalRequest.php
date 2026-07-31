<?php

namespace App\Http\Requests\Legal;

use App\Models\Project;
use App\Models\ProjectApproval;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProjectApproval::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'approval_code' => ['required', 'string', 'max:80'],
            'approval_type' => ['required', 'string', 'max:120'],
            'authority_name' => ['required', 'string', 'max:160'],
            'application_number' => ['nullable', 'string', 'max:120'],
            'applied_on' => ['nullable', 'date'],
            'approved_on' => ['nullable', 'date', 'after_or_equal:applied_on'],
            'expires_on' => ['nullable', 'date', 'after:approved_on'],
            'status' => ['required', 'string', Rule::in(['applied', 'approved'])],
            'required_for' => ['nullable', 'string', 'max:160'],
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

                if (ProjectApproval::query()
                    ->where('project_id', $this->integer('project_id'))
                    ->where('approval_code', $this->string('approval_code')->toString())
                    ->exists()) {
                    $validator->errors()->add('approval_code', 'This approval code already exists for the selected project.');
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
