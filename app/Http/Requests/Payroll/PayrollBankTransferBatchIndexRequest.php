<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollBankTransferBatch;
use App\Models\PayrollRun;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PayrollBankTransferBatchIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->truthyIncludePayload() && $this->user()?->can('viewPayload', PayrollBankTransferBatch::class) !== true) {
            return false;
        }

        return $this->user()?->can('viewAny', PayrollBankTransferBatch::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['prepared', 'released'])],
            'payroll_run_id' => ['nullable', 'integer', Rule::exists('payroll_runs', 'id')],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'include_payload' => ['nullable', 'boolean'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['status', 'payroll_run_id', 'bank_name', 'from', 'to', 'include_payload', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('payroll_run_id')) {
                return;
            }

            $payrollRun = PayrollRun::find($this->integer('payroll_run_id'));

            if ($payrollRun && ! app(CompanyScopeService::class)->allows($this->user(), $payrollRun->company_id)) {
                $validator->errors()->add('payroll_run_id', 'The selected payroll run is outside your company scope.');
            }
        });
    }

    private function truthyIncludePayload(): bool
    {
        return in_array($this->input('include_payload'), [true, 1, '1', 'true', 'on', 'yes'], true);
    }
}
