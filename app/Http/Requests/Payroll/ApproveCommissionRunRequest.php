<?php

namespace App\Http\Requests\Payroll;

use App\Models\CommissionRun;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCommissionRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $run = $this->route('commissionRun');

        return $run instanceof CommissionRun
            && $this->user()?->can('approve', $run) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
