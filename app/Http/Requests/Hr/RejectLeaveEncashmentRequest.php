<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveEncashment;
use Illuminate\Foundation\Http\FormRequest;

class RejectLeaveEncashmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $encashment = $this->route('leaveEncashment');

        return $encashment instanceof LeaveEncashment
            && $this->user()?->can('reject', $encashment) === true;
    }

    public function rules(): array
    {
        return [
            'decision_note' => ['required', 'string', 'max:1000'],
        ];
    }
}
