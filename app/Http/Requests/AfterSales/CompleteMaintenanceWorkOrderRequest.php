<?php

namespace App\Http\Requests\AfterSales;

use App\Models\MaintenanceWorkOrder;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;

class CompleteMaintenanceWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $maintenanceWorkOrder = $this->route('maintenanceWorkOrder');

        return $maintenanceWorkOrder instanceof MaintenanceWorkOrder
            && $this->user()?->can('complete', $maintenanceWorkOrder) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'completion_notes' => ['required', 'string', 'min:10', 'max:5000'],
            'actual_cost' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->maintenanceCostMaxRule()],
        ];
    }
}
