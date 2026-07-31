<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeAsset;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EmployeeAsset::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'asset_code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/', Rule::unique('employee_assets', 'asset_code')],
            'category' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:160'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'condition' => ['nullable', 'string', Rule::in(['new', 'good', 'fair', 'damaged'])],
            'estimated_value' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user) {
                    return;
                }

                $companyId = $this->filled('company_id')
                    ? $this->integer('company_id')
                    : app(CompanyScopeService::class)->companyIdFor($user);

                if ($companyId === null || $companyId === 0) {
                    $validator->errors()->add('company_id', 'A company assignment is required before creating employee assets.');

                    return;
                }

                if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }
            },
        ];
    }
}