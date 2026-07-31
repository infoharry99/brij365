<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeTaxDocument;
use Illuminate\Foundation\Http\FormRequest;

class IssueTaxDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $taxDocument = $this->route('employeeTaxDocument');

        return $taxDocument instanceof EmployeeTaxDocument
            && $this->user()?->can('issue', $taxDocument) === true;
    }

    public function rules(): array
    {
        return [
            'issue_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
