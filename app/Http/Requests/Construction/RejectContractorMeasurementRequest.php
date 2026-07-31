<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorMeasurement;
use Illuminate\Foundation\Http\FormRequest;

class RejectContractorMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $measurement = $this->route('contractorMeasurement');

        return $measurement instanceof ContractorMeasurement
            && $this->user()?->can('reject', $measurement) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
