<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveEncashment;
use Illuminate\Foundation\Http\FormRequest;

class MarkLeaveEncashmentPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $encashment = $this->route('leaveEncashment');

        return $encashment instanceof LeaveEncashment
            && $this->user()?->can('markPayroll', $encashment) === true;
    }

    public function rules(): array
    {
        return [
            'payroll_reference' => ['required', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
