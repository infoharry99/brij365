<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceShiftSwapRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelAttendanceShiftSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        $swap = $this->route('attendanceShiftSwapRequest');

        return $swap instanceof AttendanceShiftSwapRequest
            && $swap->status === 'submitted'
            && ($this->user()?->can('cancel', $swap) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'cancellation_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $swap = $this->route('attendanceShiftSwapRequest');
            if ($swap instanceof AttendanceShiftSwapRequest && (int) $swap->lock_version !== $this->integer('lock_version')) {
                $validator->errors()->add('lock_version', 'This shift swap request was changed by another user. Refresh before cancelling it.');
            }
        }];
    }
}
