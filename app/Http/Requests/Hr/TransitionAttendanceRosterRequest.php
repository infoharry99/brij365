<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceRoster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TransitionAttendanceRosterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $routeName = (string) $this->route()?->getName();
        $targetStatus = match (true) {
            Str::endsWith($routeName, '.publish') => 'published',
            Str::endsWith($routeName, '.lock') => 'locked',
            Str::endsWith($routeName, '.reopen') => 'reopened',
            Str::endsWith($routeName, '.cancel') => 'cancelled',
            default => $this->input('target_status'),
        };

        $this->merge(['target_status' => $targetStatus]);
    }

    public function authorize(): bool
    {
        $attendanceRoster = $this->route('attendanceRoster');
        $ability = match ($this->input('target_status')) {
            'published' => 'publish',
            'locked' => 'lock',
            'reopened' => 'reopen',
            'cancelled' => 'cancel',
            default => null,
        };

        return $attendanceRoster instanceof AttendanceRoster
            && $ability !== null
            && ($this->user()?->can($ability, $attendanceRoster) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'target_status' => ['required', Rule::in(['published', 'locked', 'reopened', 'cancelled'])],
            'lock_version' => ['required', 'integer', 'min:1'],
            'status_note' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('target_status'), ['reopened', 'cancelled'], true)),
                'nullable',
                'string',
                'max:2000',
            ],
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
                $validator->errors()->add('lock_version', 'This roster was changed by another user. Refresh before changing its status.');
            }

            $targetStatus = (string) $this->input('target_status');
            $allowed = match ($targetStatus) {
                'published' => $attendanceRoster->status === 'draft',
                'locked' => $attendanceRoster->status === 'published',
                'reopened' => $attendanceRoster->status === 'locked',
                'cancelled' => in_array($attendanceRoster->status, ['draft', 'published'], true),
                default => false,
            };

            if (! $allowed) {
                $validator->errors()->add('target_status', 'The requested roster status transition is not allowed from its current state.');
            }

            if ($targetStatus === 'published' && ! $attendanceRoster->entries()->exists()) {
                $validator->errors()->add('target_status', 'Add at least one roster entry before publishing.');
            }
        }];
    }
}
