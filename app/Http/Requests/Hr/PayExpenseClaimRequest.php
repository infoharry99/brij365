<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class PayExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('expenseClaim');

        return $claim instanceof \App\Models\ExpenseClaim
            && $this->user()?->can('pay', $claim) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
