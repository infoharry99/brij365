<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialVoucher;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinancialVoucherIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinancialVoucher::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'rejected', 'void'])],
            'voucher_type' => ['nullable', 'string', Rule::in(['receipt', 'payment', 'journal', 'contra', 'debit_note', 'credit_note'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['status', 'voucher_type', 'project_id', 'date_from', 'date_to', 'q', 'page'],
                );

                $user = $this->user();

                if (! $user || ! $this->filled('project_id') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $projectCompanyId = Project::query()
                    ->whereKey($this->integer('project_id'))
                    ->value('company_id');

                if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                    $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                }
            },
        ];
    }
}
