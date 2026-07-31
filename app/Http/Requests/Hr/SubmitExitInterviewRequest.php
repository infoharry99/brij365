<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeExitInterview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exitInterview = $this->route('employeeExitInterview');

        return $exitInterview instanceof EmployeeExitInterview
            && $this->user()?->can('submit', $exitInterview) === true;
    }

    public function rules(): array
    {
        return [
            'separation_reason' => ['required', 'string', Rule::in(['career_growth', 'compensation', 'relocation', 'manager_issue', 'work_environment', 'health', 'retirement', 'contract_end', 'personal', 'other'])],
            'rehire_recommendation' => ['required', 'string', Rule::in(['yes', 'no', 'conditional'])],
            'overall_experience_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'manager_relationship_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'workload_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'compensation_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'public_feedback' => ['nullable', 'string', 'max:3000'],
            'improvement_suggestions' => ['nullable', 'string', 'max:3000'],
            'confidential_responses' => ['required', 'array', 'min:1'],
            'confidential_responses.*' => ['nullable'],
            'risk_flags' => ['nullable', 'array', 'max:10'],
            'risk_flags.*' => ['string', Rule::in(['manager_concern', 'harassment_allegation', 'pay_dispute', 'policy_gap', 'culture_risk', 'retention_risk', 'client_risk', 'none'])],
            'scoring_inputs' => ['nullable', 'array'],
            'scoring_inputs.career_growth' => ['nullable', 'numeric', 'between:1,5'],
            'scoring_inputs.work_environment' => ['nullable', 'numeric', 'between:1,5'],
            'scoring_inputs.rehire_recommendation' => ['nullable', 'numeric', 'between:1,5'],
        ];
    }
}
