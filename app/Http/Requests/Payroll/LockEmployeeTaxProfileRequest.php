<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class LockEmployeeTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('lock', $this->route('employeeTaxProfile')) === true;
    }

    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
