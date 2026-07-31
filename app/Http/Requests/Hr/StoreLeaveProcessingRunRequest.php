<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveProcessingRun;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeaveProcessingRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeaveProcessingRun::class) === true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'processing_type' => ['required', 'string', Rule::in(['monthly_accrual', 'year_end'])],
            'is_dry_run' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            $companyScope = app(CompanyScopeService::class);

            if ($user && $companyScope->companyIdFor($user) === 0) {
                $validator->errors()->add('company_id', 'A company assignment is required to create leave processing runs.');
            }

            if ($user && $this->filled('company_id') && ! $companyScope->allows($user, $this->integer('company_id'))) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            if ($user && $companyScope->hasUnrestrictedCompanyScope($user) && ! $this->filled('company_id')) {
                $validator->errors()->add('company_id', 'A company is required when creating a leave processing run as a global user.');
            }
        });
    }
}
