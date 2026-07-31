<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendancePeriodLock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReopenAttendancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendancePeriodLock = $this->route('attendancePeriodLock');

        return $attendancePeriodLock instanceof AttendancePeriodLock
            && $attendancePeriodLock->status === 'finalized'
            && ($this->user()?->can('reopen', $attendancePeriodLock) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'reopen_reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $attendancePeriodLock = $this->route('attendancePeriodLock');
            if ($attendancePeriodLock instanceof AttendancePeriodLock
                && (int) $attendancePeriodLock->lock_version !== $this->integer('lock_version')) {
                $validator->errors()->add('lock_version', 'This attendance period was changed by another user. Refresh before reopening it.');
            }
        }];
    }
}
