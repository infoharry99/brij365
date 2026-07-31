<?php

namespace App\Http\Requests\Finance;

use App\Models\PaymentRequest;
use Illuminate\Foundation\Http\FormRequest;

class CancelPaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $paymentRequest = $this->route('paymentRequest');

        return $paymentRequest instanceof PaymentRequest
            && $this->user()?->can('cancel', $paymentRequest) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
