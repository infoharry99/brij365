<?php

namespace App\Http\Requests\Finance;

use App\Models\GstEntry;
use App\Models\GstReturnPeriod;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGstEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GstEntry::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'document_date' => ['required', 'date'],
            'document_number' => ['required', 'string', 'max:80'],
            'party_name' => ['required', 'string', 'max:180'],
            'party_gstin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'place_of_supply_state' => ['required', 'string', 'size:2'],
            'transaction_type' => ['required', 'string', Rule::in(['output', 'input', 'reverse_charge', 'adjustment'])],
            'hsn_sac' => ['nullable', 'string', 'max:20'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:40'],
            'taxable_amount' => ['required', 'numeric', 'min:0.01', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'cgst_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'sgst_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'igst_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'cess_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
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
                $companyScope = app(CompanyScopeService::class);
                $companyId = $user ? $companyScope->companyIdFor($user) : 0;
                $date = $this->date('document_date');

                if ($this->integer('project_id') > 0) {
                    $projectCompanyId = Project::query()
                        ->whereKey($this->integer('project_id'))
                        ->value('company_id');

                    if (! $user || ! $companyScope->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The project must belong to your company.');
                    }
                }

                if ($companyId === null || $companyId <= 0) {
                    $validator->errors()->add('document_date', 'GST entries require a valid company scope.');

                    return;
                }

                $locked = GstReturnPeriod::query()
                    ->where('company_id', $companyId)
                    ->where('period_year', (int) $date?->year)
                    ->where('period_month', (int) $date?->month)
                    ->where('status', 'locked')
                    ->exists();

                if ($locked) {
                    $validator->errors()->add('document_date', 'GST entries cannot be created for a locked return period.');
                }

                $exists = GstEntry::query()
                    ->where('company_id', $companyId)
                    ->where('document_number', $this->string('document_number')->toString())
                    ->where('transaction_type', $this->string('transaction_type')->toString())
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('document_number', 'A GST entry already exists for this document and transaction type.');
                }

                $expectedTax = round((float) $this->input('taxable_amount') * ((float) $this->input('tax_rate') / 100), 2);
                $providedTax = round((float) $this->input('cgst_amount', 0) + (float) $this->input('sgst_amount', 0) + (float) $this->input('igst_amount', 0) + (float) $this->input('cess_amount', 0), 2);

                if (abs($expectedTax - $providedTax) > 1) {
                    $validator->errors()->add('tax_rate', 'GST component total must match taxable amount and tax rate within rounding tolerance.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('place_of_supply_state')) {
            $this->merge(['place_of_supply_state' => strtoupper((string) $this->input('place_of_supply_state'))]);
        }
        if ($this->has('party_gstin')) {
            $this->merge(['party_gstin' => strtoupper((string) $this->input('party_gstin'))]);
        }
    }
}