<?php

namespace App\Http\Requests\Maintenance;

use App\Models\MaintenanceDue;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkMaintenanceDuePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $due = $this->route('maintenanceDue');

        return $due instanceof MaintenanceDue
            && $this->user()?->can('markPaid', $due) === true;
    }

    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:0.01', app(MoneyInputPolicy::class)->maintenanceCostMaxRule()],
            'payment_reference' => ['required', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $due = $this->route('maintenanceDue');

                if ($due instanceof MaintenanceDue && (float) $this->input('paid_amount') > (float) $due->balance_amount) {
                    $validator->errors()->add('paid_amount', 'Paid amount cannot exceed the outstanding maintenance balance.');
                }
            },
        ];
    }
}
