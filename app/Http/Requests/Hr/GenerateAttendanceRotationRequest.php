<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRoster;
use App\Models\AttendanceRotationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateAttendanceRotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendanceRoster = $this->route('attendanceRoster');
        $attendanceRotationRule = $this->route('attendanceRotationRule');

        return $attendanceRoster instanceof AttendanceRoster
            && $attendanceRotationRule instanceof AttendanceRotationRule
            && ($this->user()?->can('manage', $attendanceRoster) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $attendanceRoster = $this->route('attendanceRoster');
            $attendanceRotationRule = $this->route('attendanceRotationRule');
            if (! $attendanceRoster instanceof AttendanceRoster || ! $attendanceRotationRule instanceof AttendanceRotationRule) {
                return;
            }

            if ((int) $attendanceRoster->lock_version !== $this->integer('lock_version')) {
                $validator->errors()->add('lock_version', 'This roster was changed by another user. Refresh before generating rotation entries.');
            }

            if ($attendanceRoster->status !== 'draft') {
                $validator->errors()->add('start_date', 'Rotation entries may be generated only into a draft roster.');
            }

            if ($attendanceRotationRule->status !== 'active') {
                $validator->errors()->add('start_date', 'Only an active rotation rule may generate roster entries.');
            }

            if ((int) $attendanceRotationRule->company_id !== (int) $attendanceRoster->company_id) {
                $validator->errors()->add('start_date', 'The rotation rule and roster must belong to the same company.');
            }

            $startDate = $this->filled('start_date') ? $this->date('start_date') : $attendanceRoster->period_start;
            $endDate = $this->filled('end_date') ? $this->date('end_date') : $attendanceRoster->period_end;

            if ($startDate->lt($attendanceRoster->period_start) || $endDate->gt($attendanceRoster->period_end)) {
                $validator->errors()->add('start_date', 'The generation range must remain within the roster period.');
            }
        }];
    }
}
