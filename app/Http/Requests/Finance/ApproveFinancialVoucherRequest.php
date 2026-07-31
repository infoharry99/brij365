<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialVoucher;
use Illuminate\Foundation\Http\FormRequest;

class ApproveFinancialVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        $voucher = $this->route('financialVoucher');

        return $voucher instanceof FinancialVoucher && ($this->user()?->can('approve', $voucher) ?? false);
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
