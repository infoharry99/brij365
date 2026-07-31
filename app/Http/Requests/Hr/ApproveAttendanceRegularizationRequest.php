<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRegularizationRequest as AttendanceRegularization;
use Illuminate\Foundation\Http\FormRequest;

class ApproveAttendanceRegularizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $regularization = $this->route('regularization');

        return $regularization instanceof AttendanceRegularization
            && ($this->user()?->can('approve', $regularization) ?? false);
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
