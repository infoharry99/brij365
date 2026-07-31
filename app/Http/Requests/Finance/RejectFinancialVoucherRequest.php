<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialVoucher;
use Illuminate\Foundation\Http\FormRequest;

class RejectFinancialVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('financialVoucher');

        return $voucher instanceof FinancialVoucher && ($this->user()?->can('reject', $voucher) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
