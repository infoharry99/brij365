<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorMeasurement;
use Illuminate\Foundation\Http\FormRequest;

class ApproveContractorMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $measurement = $this->route('contractorMeasurement');

        return $measurement instanceof ContractorMeasurement
            && $this->user()?->can('approve', $measurement) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
