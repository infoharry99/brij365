<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorBill;
use Illuminate\Foundation\Http\FormRequest;

class ApproveContractorBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('contractorBill');

        return $bill instanceof ContractorBill
            && $this->user()?->can('approve', $bill) === true;
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
