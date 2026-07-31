<?php

namespace App\Http\Requests\Finance;

use App\Models\GstReturnPeriod;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PrepareGstReturnPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GstReturnPeriod::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $companyId = $user ? app(CompanyScopeService::class)->companyIdFor($user) : 0;

            if ($companyId === null || $companyId <= 0) {
                $validator->errors()->add('period_month', 'GST return periods require a valid company scope.');

                return;
            }

            $exists = GstReturnPeriod::query()
                ->where('company_id', $companyId)
                ->where('period_year', $this->integer('period_year'))
                ->where('period_month', $this->integer('period_month'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('period_month', 'A GST return period already exists for this company and month.');
            }
        });
    }
}
