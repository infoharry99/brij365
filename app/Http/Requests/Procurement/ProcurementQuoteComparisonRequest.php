<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseRequisition;
use Illuminate\Foundation\Http\FormRequest;

class ProcurementQuoteComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseRequisition = $this->route('purchaseRequisition');

        return $purchaseRequisition instanceof PurchaseRequisition
            && $this->user()?->can('view', $purchaseRequisition) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
