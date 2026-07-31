<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $leaveRequest = $this->route('leaveRequest');

        return $leaveRequest instanceof LeaveRequest
            && ($this->user()?->can('approve', $leaveRequest) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
