<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class RejectExpenseClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $claim = $this->route('expenseClaim');

        return $claim instanceof \App\Models\ExpenseClaim
            && $this->user()?->can('reject', $claim) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
