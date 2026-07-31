<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecoverEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('employeeAsset');

        return $asset instanceof \App\Models\EmployeeAsset
            && $this->user()?->can('recover', $asset) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recovered_on' => ['nullable', 'date', 'before_or_equal:today'],
            'condition' => ['required', 'string', Rule::in(['new', 'good', 'fair', 'damaged'])],
            'status' => ['nullable', 'string', Rule::in(['recovered', 'retired', 'lost'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
