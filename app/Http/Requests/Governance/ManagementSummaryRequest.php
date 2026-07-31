<?php

namespace App\Http\Requests\Governance;

use Illuminate\Foundation\Http\FormRequest;

class ManagementSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['sometimes', 'string', 'in:json,csv'],
        ];
    }
}
