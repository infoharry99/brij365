<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollBankTransferBatch;
use Illuminate\Foundation\Http\FormRequest;

class ReleasePayrollBankTransferBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('payrollBankTransferBatch');

        return $batch instanceof PayrollBankTransferBatch
            && $this->user()?->can('release', $batch) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'release_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
