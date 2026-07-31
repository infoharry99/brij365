<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorBill;
use App\Models\ContractorMeasurement;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContractorBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContractorBill::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contractor_measurement_id' => ['required', 'integer', Rule::exists('contractor_measurements', 'id')],
            'bill_date' => ['required', 'date', 'before_or_equal:today'],
            'retention_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'deductions' => ['nullable', 'array', 'max:30'],
            'deductions.*.code' => ['required_with:deductions', 'string', 'max:40', 'regex:/^[A-Z0-9_\\-\\.]+$/'],
            'deductions.*.description' => ['required_with:deductions', 'string', 'max:255'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();
                $measurement = ContractorMeasurement::query()->whereKey($this->integer('contractor_measurement_id'))->first();

                if (! $user || ! $measurement || ! app(CompanyScopeService::class)->allows($user, $measurement->company_id)) {
                    $validator->errors()->add('contractor_measurement_id', 'The selected contractor measurement is not available for your company.');

                    return;
                }

                if ($measurement->status !== 'approved') {
                    $validator->errors()->add('contractor_measurement_id', 'Only approved contractor measurements can be billed.');
                }

                $billExists = ContractorBill::query()
                    ->where('contractor_measurement_id', $measurement->id)
                    ->exists();

                if ($billExists) {
                    $validator->errors()->add('contractor_measurement_id', 'A contractor bill already exists for this measurement.');
                }

                $rules = app(SystemSettingResolver::class)->value($measurement->company_id, 'construction.contractor_billing', [
                    'default_retention_percent' => 5,
                    'max_retention_percent' => 10,
                    'max_deduction_percent_of_gross' => 30,
                ]);

                $maxRetention = (float) ($rules['max_retention_percent'] ?? 10);
                $retentionPercent = $this->filled('retention_percent')
                    ? (float) $this->input('retention_percent')
                    : (float) ($rules['default_retention_percent'] ?? 5);

                if ($retentionPercent > $maxRetention) {
                    $validator->errors()->add('retention_percent', "Retention percent cannot exceed configured limit of {$maxRetention}%.");
                }

                $grossAmount = (float) $measurement->certified_total;
                $deductionAmount = collect($this->input('deductions', []))->sum(fn (array $deduction): float => (float) ($deduction['amount'] ?? 0));
                $maxDeductionPercent = (float) ($rules['max_deduction_percent_of_gross'] ?? 30);
                $maxDeductionAmount = round($grossAmount * $maxDeductionPercent / 100, 2);

                if ($deductionAmount > $maxDeductionAmount) {
                    $validator->errors()->add('deductions', "Total deductions cannot exceed configured limit of {$maxDeductionPercent}% of gross amount.");
                }
            },
        ];
    }
}
