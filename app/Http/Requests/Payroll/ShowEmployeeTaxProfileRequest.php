<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

final class ShowEmployeeTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('employeeTaxProfile')) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
