<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Branch;
use App\Models\JobOpening;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', JobOpening::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:120'],
            'designation' => ['required', 'string', 'max:120'],
            'positions' => ['required', 'integer', 'min:1', 'max:200'],
            'employment_type' => ['required', 'string', Rule::in(['full_time', 'part_time', 'contract', 'intern', 'consultant'])],
            'work_location' => ['nullable', 'string', 'max:255'],
            'budget_min_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->ctcAmountMaxRule()],
            'budget_max_ctc' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->ctcAmountMaxRule()],
            'target_hiring_date' => ['nullable', 'date', 'after_or_equal:today'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:120'],
            'business_justification' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [$this->validateCompanyScope(...)];
    }

    protected function validateCompanyScope(Validator $validator): void
    {
        $actor = $this->user();
        $companyId = $this->integer('company_id');

        if (! $actor || ! app(CompanyScopeService::class)->allows($actor, $companyId)) {
            $validator->errors()->add('company_id', 'Recruitment users can create requisitions only in their own company.');

            return;
        }

        if ($this->filled('branch_id')) {
            $branch = Branch::find($this->integer('branch_id'));

            if ($branch && (int) $branch->company_id !== $companyId) {
                $validator->errors()->add('branch_id', 'The selected branch does not belong to the requisition company.');
            }
        }

        if ($this->filled('project_id')) {
            $project = Project::find($this->integer('project_id'));

            if ($project && (int) $project->company_id !== $companyId) {
                $validator->errors()->add('project_id', 'The selected project does not belong to the requisition company.');
            }
        }

        if ($this->filled('budget_min_ctc') && $this->filled('budget_max_ctc') && (float) $this->input('budget_max_ctc') < (float) $this->input('budget_min_ctc')) {
            $validator->errors()->add('budget_max_ctc', 'The maximum CTC must be greater than or equal to the minimum CTC.');
        }
    }
}
