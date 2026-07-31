<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

class PreparePayrollBankTransferBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payrollRun = $this->route('payrollRun');

        return $payrollRun instanceof PayrollRun
            && $this->user()?->can('create', [\App\Models\PayrollBankTransferBatch::class, $payrollRun]) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:120'],
            'payment_date' => ['required', 'date', 'after_or_equal:today'],
            'debit_account_number' => ['required', 'string', 'min:6', 'max:32', 'regex:/^[0-9]+$/'],
            'narration' => ['nullable', 'string', 'max:160'],
        ];
    }
}
