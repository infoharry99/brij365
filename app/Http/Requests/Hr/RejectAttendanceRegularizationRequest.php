<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRegularizationRequest;
use Illuminate\Foundation\Http\FormRequest;

class RejectAttendanceRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $regularization = $this->route('regularization');

        return $regularization instanceof AttendanceRegularizationRequest
            && ($this->user()?->can('reject', $regularization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_note' => ['required', 'string', 'max:2000'],
        ];
    }
}
