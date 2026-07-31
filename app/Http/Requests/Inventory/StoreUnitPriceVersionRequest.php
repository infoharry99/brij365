<?php

namespace App\Http\Requests\Inventory;

use App\Models\ProjectUnit;
use App\Models\UnitPriceVersion;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUnitPriceVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UnitPriceVersion::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_unit_id' => ['required', 'integer', 'exists:project_units,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'base_rate' => ['required', 'numeric', 'min:0.01', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'floor_premium' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'location_premium' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'parking_charges' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'other_charges' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'charge_breakup' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            $unit = $this->filled('project_unit_id')
                ? ProjectUnit::query()->whereKey($this->integer('project_unit_id'))->first()
                : null;

            if (! $user || ! $unit) {
                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $unit->company_id)) {
                $validator->errors()->add('project_unit_id', 'The selected unit is not available for your company.');
            }
        });
    }
}
