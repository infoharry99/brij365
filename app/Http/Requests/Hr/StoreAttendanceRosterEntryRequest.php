<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRoster;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceShift;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRosterEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attendanceRoster = $this->route('attendanceRoster');

        return $attendanceRoster instanceof AttendanceRoster
            && ($this->user()?->can('manage', $attendanceRoster) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:1'],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'attendance_shift_id' => ['nullable', 'integer', Rule::exists('attendance_shifts', 'id')],
            'work_date' => ['required', 'date'],
            'entry_type' => ['required', Rule::in(['shift', 'off', 'holiday'])],
            'metadata' => ['nullable', 'array'],
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
            if (! $attendanceRoster instanceof AttendanceRoster) {
                return;
            }

            if ((int) $attendanceRoster->lock_version !== $this->integer('lock_version')) {
                $validator->errors()->add('lock_version', 'This roster was changed by another user. Refresh before adding an entry.');
            }

            if ($attendanceRoster->status !== 'draft') {
                $validator->errors()->add('work_date', 'Entries may be added only while the roster is in draft.');
            }

            $workDate = $this->date('work_date');
            if ($workDate->lt($attendanceRoster->period_start) || $workDate->gt($attendanceRoster->period_end)) {
                $validator->errors()->add('work_date', 'The work date must fall within the roster period.');
            }

            $employee = Employee::find($this->integer('employee_id'));
            if ($employee && (int) $employee->company_id !== (int) $attendanceRoster->company_id) {
                $validator->errors()->add('employee_id', 'The selected employee does not belong to the roster company.');
            }

            if ($employee && $employee->status !== 'active') {
                $validator->errors()->add('employee_id', 'Only active employees may be added to a roster.');
            }

            $entryType = (string) $this->input('entry_type');
            $shift = $this->filled('attendance_shift_id')
                ? AttendanceShift::find($this->integer('attendance_shift_id'))
                : null;

            if ($entryType === 'shift' && ! $shift) {
                $validator->errors()->add('attendance_shift_id', 'A shift is required for a working roster entry.');
            }

            if ($entryType !== 'shift' && $this->filled('attendance_shift_id')) {
                $validator->errors()->add('attendance_shift_id', 'Off-day and holiday entries cannot carry a working shift.');
            }

            if ($shift && (int) $shift->company_id !== (int) $attendanceRoster->company_id) {
                $validator->errors()->add('attendance_shift_id', 'The selected shift does not belong to the roster company.');
            }

            if ($shift && ! $shift->is_active) {
                $validator->errors()->add('attendance_shift_id', 'Only active shifts may be used in a roster.');
            }

            $duplicateExists = AttendanceRosterEntry::query()
                ->where('attendance_roster_id', $attendanceRoster->id)
                ->where('employee_id', $this->integer('employee_id'))
                ->whereDate('work_date', $workDate->toDateString())
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('work_date', 'This employee already has an entry for the selected roster date.');
            }
        }];
    }
}
