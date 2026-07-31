<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollRun;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payrollRun = $this->route('payrollRun');

        return $payrollRun instanceof PayrollRun
            && $this->user()?->can('approve', $payrollRun) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
