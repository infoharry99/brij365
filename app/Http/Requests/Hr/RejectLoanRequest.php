<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class RejectLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $loan = $this->route('employeeLoan');
        return $loan instanceof \App\Models\EmployeeLoan && $this->user()?->can('reject', $loan) === true;
    }

    public function rules(): array { return ['decision_note' => ['required', 'string', 'max:1000']]; }
}
