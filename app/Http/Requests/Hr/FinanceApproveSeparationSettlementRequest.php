<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeSeparationSettlement;
use Illuminate\Foundation\Http\FormRequest;

class FinanceApproveSeparationSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $settlement = $this->route('employeeSeparationSettlement');

        return $settlement instanceof EmployeeSeparationSettlement
            && $this->user()?->can('financeApprove', $settlement) === true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
