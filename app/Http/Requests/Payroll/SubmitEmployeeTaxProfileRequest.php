<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitEmployeeTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->route('employeeTaxProfile')) === true;
    }

    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
