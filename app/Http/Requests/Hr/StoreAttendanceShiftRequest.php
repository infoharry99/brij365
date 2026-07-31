<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendanceShift;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceShiftRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('segments'))) {
            return;
        }

        $segments = collect($this->input('segments'))
            ->filter(fn ($segment): bool => is_array($segment) && collect($segment)->contains(
                fn ($value): bool => $value !== null && trim((string) $value) !== '',
            ))
            ->values()
            ->all();
        $this->merge(['segments' => $segments]);
    }

    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'is_overnight' => ['required', 'boolean'],
            'late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'early_leave_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'half_day_threshold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'full_day_threshold_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'rules' => ['nullable', 'array'],
            'rules.shift_type' => ['nullable', Rule::in(['fixed', 'flexible', 'rotational', 'night', 'split'])],
            'rules.weekly_off_policy' => ['nullable', 'string', 'max:120'],
            'rules.overtime_enabled' => ['nullable', 'boolean'],
            'rules.geofence_required' => ['nullable', 'boolean'],
            'segments' => ['nullable', 'array', 'max:8'],
            'segments.*.label' => ['nullable', 'string', 'max:80'],
            'segments.*.starts_at' => ['required_with:segments', 'date_format:H:i'],
            'segments.*.ends_at' => ['required_with:segments', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if (! $user) {
                return;
            }

            $companyId = $this->integer('company_id') ?: $user->company_id;
            if (! $companyId) {
                $validator->errors()->add('company_id', 'Company is required for attendance shift creation.');

                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            $duplicateExists = AttendanceShift::query()
                ->withTrashed()
                ->where('company_id', $companyId)
                ->where('code', strtoupper((string) $this->input('code')))
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('code', 'An attendance shift with this code already exists for the selected company.');
            }

            if ($this->input('starts_at') === $this->input('ends_at')) {
                $validator->errors()->add('ends_at', 'Shift end time must be different from shift start time.');
            }

            if ($this->boolean('is_overnight') === false && $this->input('ends_at') < $this->input('starts_at')) {
                $validator->errors()->add('is_overnight', 'Enable overnight shift when end time is earlier than start time.');
            }

            if ($this->integer('half_day_threshold_minutes') > $this->integer('full_day_threshold_minutes')) {
                $validator->errors()->add('half_day_threshold_minutes', 'Half-day threshold cannot exceed full-day threshold.');
            }

            $segments = array_values((array) $this->input('segments', []));
            $isSplit = $this->input('rules.shift_type') === 'split';
            if ($isSplit && count($segments) < 2) {
                $validator->errors()->add('segments', 'A split shift requires at least two working segments.');

                return;
            }
            if (! $isSplit && $segments !== []) {
                $validator->errors()->add('segments', 'Working segments may be configured only when the shift type is split.');

                return;
            }
            if ($segments === []) {
                return;
            }

            $shiftStart = $this->timeToMinutes((string) $this->input('starts_at'));
            $shiftEnd = $this->timeToMinutes((string) $this->input('ends_at'));
            $overnight = $this->boolean('is_overnight') || $shiftEnd <= $shiftStart;
            if ($overnight) {
                $shiftEnd += 1440;
            }

            $intervals = [];
            foreach ($segments as $index => $segment) {
                $start = $this->timeToMinutes((string) ($segment['starts_at'] ?? ''));
                $end = $this->timeToMinutes((string) ($segment['ends_at'] ?? ''));
                if ($overnight && $start < $shiftStart) {
                    $start += 1440;
                }
                if ($overnight && $end <= $start) {
                    $end += 1440;
                }
                if (! $overnight && $end <= $start) {
                    $validator->errors()->add("segments.$index.ends_at", 'A same-day segment must end after it starts.');
                    continue;
                }
                if ($start < $shiftStart || $end > $shiftEnd) {
                    $validator->errors()->add("segments.$index.starts_at", 'Every segment must stay within the shift start and end time.');
                }
                $intervals[] = ['index' => $index, 'start' => $start, 'end' => $end];
            }

            usort($intervals, fn (array $left, array $right): int => $left['start'] <=> $right['start']);
            for ($index = 1; $index < count($intervals); $index++) {
                if ($intervals[$index]['start'] < $intervals[$index - 1]['end']) {
                    $validator->errors()->add(
                        'segments.'.$intervals[$index]['index'].'.starts_at',
                        'Split-shift working segments cannot overlap.',
                    );
                }
            }
        });
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time) + [0, 0]);

        return ($hours * 60) + $minutes;
    }
}
