<?php

namespace App\Http\Requests\Hr;

use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;

class ApproveExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('expenseClaim');

        return $claim instanceof \App\Models\ExpenseClaim
            && $this->user()?->can('approve', $claim) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approved_amount' => ['required', 'numeric', 'min:1', app(MoneyInputPolicy::class)->hrAmountMaxRule()],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
