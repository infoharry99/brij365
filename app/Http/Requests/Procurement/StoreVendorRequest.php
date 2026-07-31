<?php

namespace App\Http\Requests\Procurement;

use App\Models\Company;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Vendor::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'vendor_code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\\-\\.]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', 'string', Rule::in(['material', 'contractor', 'service', 'consultant'])],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\\-\\s()]+$/'],
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9A-Z]{15}$/'],
            'pan' => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.pin_code' => ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Z\\-\\s]+$/'],
            'bank_details' => ['nullable', 'array'],
            'bank_details.account_holder' => ['nullable', 'string', 'max:160'],
            'bank_details.account_number' => ['nullable', 'string', 'max:34', 'regex:/^[0-9A-Z\\-]+$/'],
            'bank_details.ifsc' => ['nullable', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_details.account_masked' => ['nullable', 'string', 'max:40'],
            'compliance_documents' => ['nullable', 'array'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'blocked'])],
            'metadata' => ['nullable', 'array'],
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
                $companyId = $this->targetCompanyId();

                if (! $user || ! $companyId || ! app(CompanyScopeService::class)->allows($user, $companyId)) {
                    $validator->errors()->add('company_id', 'The selected company is not available for vendor creation.');

                    return;
                }

                $companyActive = Company::query()
                    ->whereKey($companyId)
                    ->where('status', 'active')
                    ->exists();

                if (! $companyActive) {
                    $validator->errors()->add('company_id', 'The selected company is not active.');
                }

                $vendorCodeExists = Vendor::query()
                    ->where('company_id', $companyId)
                    ->where('vendor_code', strtoupper((string) $this->input('vendor_code')))
                    ->exists();

                if ($vendorCodeExists) {
                    $validator->errors()->add('vendor_code', 'This vendor code already exists for the selected company.');
                }

                if ($this->filled('gstin')) {
                    $gstinExists = Vendor::query()
                        ->where('company_id', $companyId)
                        ->where('gstin', strtoupper((string) $this->input('gstin')))
                        ->exists();

                    if ($gstinExists) {
                        $validator->errors()->add('gstin', 'This GSTIN already exists for the selected company.');
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $updates = [];

        foreach (['vendor_code', 'gstin', 'pan'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $updates[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }

        if (isset($updates['bank_details']) && is_array($updates['bank_details'])) {
            $updates['bank_details'] = $this->normalizeBankDetails($updates['bank_details']);
        } elseif ($this->has('bank_details') && is_array($this->input('bank_details'))) {
            $updates['bank_details'] = $this->normalizeBankDetails($this->input('bank_details'));
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
    }

    private function targetCompanyId(): ?int
    {
        if ($this->filled('company_id')) {
            return $this->integer('company_id');
        }

        return $this->user()?->company_id ? (int) $this->user()->company_id : null;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function normalizeBankDetails(array $details): array
    {
        if (isset($details['ifsc']) && is_scalar($details['ifsc'])) {
            $details['ifsc'] = strtoupper(trim((string) $details['ifsc']));
        }

        return $details;
    }
}
