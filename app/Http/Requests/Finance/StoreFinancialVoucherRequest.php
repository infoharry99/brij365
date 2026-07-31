<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialVoucher;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialVoucher::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'voucher_type' => ['required', 'string', Rule::in(['receipt', 'payment', 'journal', 'contra', 'debit_note', 'credit_note'])],
            'voucher_date' => ['required', 'date'],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'reference_number' => ['nullable', 'string', 'max:120'],
            'narration' => ['required', 'string', 'max:5000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'lines.*.account_code' => ['required', 'string', 'max:64'],
            'lines.*.account_name' => ['required', 'string', 'max:255'],
            'lines.*.line_type' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'lines.*.party_type' => ['nullable', 'string', 'max:255'],
            'lines.*.party_id' => ['nullable', 'integer', 'min:1'],
            'lines.*.cost_center' => ['nullable', 'string', 'max:120'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'lines.*.description' => ['nullable', 'string', 'max:1000'],
            'lines.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);

                if ($companyScope->companyIdFor($actor) === 0) {
                    $validator->errors()->add('company_id', 'A company assignment is required to submit financial vouchers.');
                }

                if ($this->filled('company_id') && ! $companyScope->allows($actor, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }

                $projectIds = collect($this->input('lines', []))
                    ->pluck('project_id')
                    ->push($this->input('project_id'))
                    ->filter()
                    ->unique()
                    ->values();

                if ($projectIds->isEmpty()) {
                    if ($companyScope->hasUnrestrictedCompanyScope($actor) && ! $this->filled('company_id')) {
                        $validator->errors()->add('company_id', 'A company is required when creating a company-level voucher as a global user.');
                    }

                    return;
                }

                $projectCompanies = Project::query()
                    ->whereIn('id', $projectIds->all())
                    ->pluck('company_id', 'id');

                $invalidProjectExists = $projectCompanies
                    ->contains(fn ($companyId): bool => ! $companyScope->allows($actor, $companyId));

                if ($invalidProjectExists) {
                    $validator->errors()->add('project_id', 'Voucher projects must belong to your company.');
                }

                if ($this->filled('company_id')) {
                    $companyId = $this->integer('company_id');
                    $mismatchedProjectExists = $projectCompanies
                        ->contains(fn ($projectCompanyId): bool => (int) $projectCompanyId !== $companyId);

                    if ($mismatchedProjectExists) {
                        $validator->errors()->add('company_id', 'The selected company must match all voucher projects.');
                    }
                }

                if (! $this->filled('company_id') && $projectCompanies->unique()->count() > 1) {
                    $validator->errors()->add('company_id', 'A company is required when voucher lines reference projects from multiple companies.');
                }
            },
        ];
    }
}
