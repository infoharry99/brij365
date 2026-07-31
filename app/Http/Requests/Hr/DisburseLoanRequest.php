<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class DisburseLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $loan = $this->route('employeeLoan');
        return $loan instanceof \App\Models\EmployeeLoan && $this->user()?->can('disburse', $loan) === true;
    }

    public function rules(): array { return ['payment_reference' => ['nullable', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:1000']]; }
}
