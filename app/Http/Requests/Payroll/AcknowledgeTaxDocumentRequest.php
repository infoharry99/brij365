<?php

namespace App\Http\Requests\Payroll;

use App\Models\EmployeeTaxDocument;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeTaxDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $taxDocument = $this->route('employeeTaxDocument');

        return $taxDocument instanceof EmployeeTaxDocument
            && $this->user()?->can('acknowledge', $taxDocument) === true;
    }

    public function rules(): array
    {
        return [
            'employee_acknowledgement_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
