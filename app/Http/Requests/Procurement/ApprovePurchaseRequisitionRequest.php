<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseRequisition;
use Illuminate\Foundation\Http\FormRequest;

class ApprovePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('purchaseRequisition');

        return $requisition instanceof PurchaseRequisition
            && $this->user()?->can('approve', $requisition) === true;
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
