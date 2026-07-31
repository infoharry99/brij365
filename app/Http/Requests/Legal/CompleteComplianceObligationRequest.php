<?php

namespace App\Http\Requests\Legal;

use App\Models\ComplianceObligation;
use Illuminate\Foundation\Http\FormRequest;

class CompleteComplianceObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complianceObligation = $this->route('complianceObligation');

        return $complianceObligation instanceof ComplianceObligation
            && $this->user()?->can('complete', $complianceObligation) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'evidence_document_reference' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
