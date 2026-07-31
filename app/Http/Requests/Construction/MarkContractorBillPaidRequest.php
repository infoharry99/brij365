<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorBill;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkContractorBillPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bill = $this->route('contractorBill');

        return $bill instanceof ContractorBill
            && $this->user()?->can('markPaid', $bill) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paid_amount' => ['required', 'numeric', 'min:0.01', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['required', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $bill = $this->route('contractorBill');

                if (! $bill instanceof ContractorBill) {
                    return;
                }

                if ((float) $this->input('paid_amount') > (float) $bill->balance_amount) {
                    $validator->errors()->add('paid_amount', 'Paid amount cannot exceed the current contractor bill balance.');
                }
            },
        ];
    }
}
