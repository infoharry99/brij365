<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceShiftSwapRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DecideAttendanceShiftSwapRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $routeName = (string) $this->route()?->getName();
        $decision = match (true) {
            Str::endsWith($routeName, '.approve') => 'approved',
            Str::endsWith($routeName, '.reject') => 'rejected',
            default => $this->input('decision'),
        };

        $this->merge(['decision' => $decision]);
    }

    public function authorize(): bool
    {
        $swap = $this->route('attendanceShiftSwapRequest');
        $user = $this->user();
        $ability = match ($this->input('decision')) {
            'approved' => 'approve',
            'rejected' => 'reject',
            default => null,
        };

        return $swap instanceof AttendanceShiftSwapRequest
            && $user !== null
            && $ability !== null
            && $swap->status === 'submitted'
            && (int) $swap->requested_by_user_id !== (int) $user->id
            && $user->can($ability, $swap);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => [
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'rejected'),
                'nullable',
                'string',
                'max:2000',
            ],
            'lock_version' => ['required', 'integer', 'min:1'],
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
                $validator->errors()->add('lock_version', 'This shift swap request was changed by another user. Refresh before deciding it.');
            }
        }];
    }
}
