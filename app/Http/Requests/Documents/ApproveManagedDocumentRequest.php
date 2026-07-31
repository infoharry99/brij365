<?php

namespace App\Http\Requests\Documents;

use App\Models\ManagedDocument;
use Illuminate\Foundation\Http\FormRequest;

class ApproveManagedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $managedDocument = $this->route('managedDocument');

        return $managedDocument instanceof ManagedDocument
            && ($this->user()?->can('approve', $managedDocument) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
