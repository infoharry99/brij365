<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeConfirmationCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DecideConfirmationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('employeeConfirmationCase');

        return $case instanceof EmployeeConfirmationCase
            && $this->user()?->can('decide', $case) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hr_decision' => ['required', 'string', Rule::in(['confirm', 'extend', 'reject'])],
            'hr_comments' => ['required', 'string', 'max:3000'],
            'confirmation_effective_on' => ['nullable', 'date'],
            'extended_until' => ['nullable', 'date', 'after:today'],
            'confirmation_letter_reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [$this->validateDecisionRequirements(...)];
    }

    protected function validateDecisionRequirements(Validator $validator): void
    {
        $decision = $this->string('hr_decision')->toString();

        if ($decision === 'confirm' && ! $this->filled('confirmation_effective_on')) {
            $validator->errors()->add('confirmation_effective_on', 'Confirmation effective date is required when confirming an employee.');
        }

        if ($decision === 'confirm' && ! $this->filled('confirmation_letter_reference')) {
            $validator->errors()->add('confirmation_letter_reference', 'Confirmation letter reference is required when confirming an employee.');
        }

        if ($decision === 'extend' && ! $this->filled('extended_until')) {
            $validator->errors()->add('extended_until', 'Extended probation end date is required when extending probation.');
        }
    }
}
