<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeSeparationSettlement;
use Illuminate\Foundation\Http\FormRequest;

class CompleteSeparationSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settlement = $this->route('employeeSeparationSettlement');

        return $settlement instanceof EmployeeSeparationSettlement
            && $this->user()?->can('complete', $settlement) === true;
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
