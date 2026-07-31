<?php

namespace App\Http\Requests\Buyer;

use App\Models\PaymentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentRequest = $this->route('paymentRequest');

        return $paymentRequest instanceof PaymentRequest
            && $this->user()?->can('pay', $paymentRequest) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_mode' => ['required', 'string', Rule::in(['upi', 'card', 'netbanking', 'wallet'])],
            'instrument_number' => ['required', 'string', 'max:120'],
            'gateway_response_code' => ['nullable', 'string', 'max:80'],
        ];
    }
}
