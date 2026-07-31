<?php

namespace App\Http\Requests\Procurement;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor
            && $this->user()?->can('update', $vendor) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'vendor_type' => ['sometimes', 'required', 'string', Rule::in(['material', 'contractor', 'service', 'consultant'])],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9+\\-\\s()]+$/'],
            'gstin' => ['sometimes', 'nullable', 'string', 'size:15', 'regex:/^[0-9A-Z]{15}$/'],
            'pan' => ['sometimes', 'nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:120'],
            'address.state' => ['nullable', 'string', 'max:120'],
            'address.pin_code' => ['nullable', 'string', 'max:12', 'regex:/^[0-9A-Z\\-\\s]+$/'],
            'bank_details' => ['sometimes', 'nullable', 'array'],
            'bank_details.account_holder' => ['nullable', 'string', 'max:160'],
            'bank_details.account_number' => ['nullable', 'string', 'max:34', 'regex:/^[0-9A-Z\\-]+$/'],
            'bank_details.ifsc' => ['nullable', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_details.account_masked' => ['nullable', 'string', 'max:40'],
            'compliance_documents' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $vendor = $this->route('vendor');

                if (! $vendor instanceof Vendor) {
                    return;
                }

                if ($this->filled('gstin')) {
                    $gstinExists = Vendor::query()
                        ->where('company_id', $vendor->company_id)
                        ->where('gstin', strtoupper((string) $this->input('gstin')))
                        ->whereKeyNot($vendor->id)
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

        foreach (['gstin', 'pan'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $updates[$field] = strtoupper(trim((string) $this->input($field)));
            }
        }

        if ($this->has('bank_details') && is_array($this->input('bank_details'))) {
            $updates['bank_details'] = $this->normalizeBankDetails($this->input('bank_details'));
        }

        if ($updates !== []) {
            $this->merge($updates);
        }
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
