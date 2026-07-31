<?php

namespace App\Http\Requests\Hr;

use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $loan = $this->route('employeeLoan');
        return $loan instanceof \App\Models\EmployeeLoan && $this->user()?->can('approve', $loan) === true;
    }

    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:1000', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'repayment_starts_on' => ['required', 'date'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
