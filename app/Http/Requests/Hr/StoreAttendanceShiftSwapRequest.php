<?php

namespace App\Http\Requests\Hr;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceShiftSwapRequest;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceShiftSwapRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['source', 'target'] as $side) {
            $field = $side.'_roster_entry_id';
            $value = $this->input($field);

            if (! is_string($value) || ! str_contains($value, ':')) {
                continue;
            }

            [$entryId, $lockVersion] = array_pad(explode(':', $value, 2), 2, null);
            $normalized[$field] = $entryId;
            $normalized[$side.'_entry_lock_version'] = $lockVersion;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->can('create', AttendanceShiftSwapRequest::class)) {
            return false;
        }

        if ($user->hasPermission('attendance.manage') || $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE)) {
            return true;
        }

        $sourceEntry = AttendanceRosterEntry::with('employee')->find($this->input('source_roster_entry_id'));

        return $sourceEntry?->employee?->user_id === $user->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_roster_entry_id' => ['required', 'integer', Rule::exists('attendance_roster_entries', 'id')],
            'target_roster_entry_id' => [
                'required',
                'integer',
                'different:source_roster_entry_id',
                Rule::exists('attendance_roster_entries', 'id'),
            ],
            'source_entry_lock_version' => ['required', 'integer', 'min:1'],
            'target_entry_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sourceEntry = AttendanceRosterEntry::with(['roster', 'employee'])->find($this->integer('source_roster_entry_id'));
            $targetEntry = AttendanceRosterEntry::with(['roster', 'employee'])->find($this->integer('target_roster_entry_id'));
            $user = $this->user();

            if (! $sourceEntry || ! $targetEntry || ! $user) {
                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $sourceEntry->company_id)) {
                $validator->errors()->add('source_roster_entry_id', 'The source roster entry is outside your company scope.');
            }

            if ((int) $sourceEntry->company_id !== (int) $targetEntry->company_id) {
                $validator->errors()->add('target_roster_entry_id', 'Both roster entries must belong to the same company.');
            }

            if ((int) $sourceEntry->employee_id === (int) $targetEntry->employee_id) {
                $validator->errors()->add('target_roster_entry_id', 'A shift swap must involve two different employees.');
            }

            if ($sourceEntry->entry_type !== 'shift' || $targetEntry->entry_type !== 'shift') {
                $validator->errors()->add('target_roster_entry_id', 'Only working shift entries may be swapped.');
            }

            if ($sourceEntry->roster?->status !== 'published' || $targetEntry->roster?->status !== 'published') {
                $validator->errors()->add('target_roster_entry_id', 'Both roster entries must be published and unlocked before a swap may be requested.');
            }

            if ((int) $sourceEntry->lock_version !== $this->integer('source_entry_lock_version')) {
                $validator->errors()->add('source_entry_lock_version', 'The source roster entry changed. Refresh before requesting the swap.');
            }

            if ((int) $targetEntry->lock_version !== $this->integer('target_entry_lock_version')) {
                $validator->errors()->add('target_entry_lock_version', 'The target roster entry changed. Refresh before requesting the swap.');
            }

            if (! $user->hasPermission('attendance.manage') && ! $user->hasPermission(LogicCenterPermissions::ROSTER_MANAGE) && (int) $sourceEntry->employee?->user_id !== (int) $user->id) {
                $validator->errors()->add('source_roster_entry_id', 'You may request a shift swap only from your own roster entry.');
            }

            $selectedIds = [$sourceEntry->id, $targetEntry->id];
            $pendingExists = AttendanceShiftSwapRequest::query()
                ->where('status', 'submitted')
                ->where(function ($query) use ($selectedIds): void {
                    $query->whereIn('source_roster_entry_id', $selectedIds)
                        ->orWhereIn('target_roster_entry_id', $selectedIds);
                })
                ->exists();

            if ($pendingExists) {
                $validator->errors()->add('target_roster_entry_id', 'One of the selected roster entries already has a pending swap request.');
            }
        }];
    }
}
