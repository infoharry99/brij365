<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveEncashment;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveEncashmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $encashment = $this->route('leaveEncashment');

        return $encashment instanceof LeaveEncashment
            && $this->user()?->can('approve', $encashment) === true;
    }

    public function rules(): array
    {
        return [
            'approved_days' => ['nullable', 'numeric', 'min:0.5', 'max:365'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
