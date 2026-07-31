<?php

namespace App\Http\Requests\Scoring;

use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

final class StoreAttendanceRosterRulePackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasPermission(LogicCenterPermissions::ROSTER_MANAGE) === true
            || $user?->hasPermission('attendance.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'setting_key' => ['required', 'string', Rule::in([
                AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
                AttendanceRosterRulePackValidator::ROSTER_KEY,
            ])],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'effective_from' => ['required', 'date'],
            'value' => ['required', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $scope = app(CompanyScopeService::class);

                if (! $user || $scope->settingCompanyIdFor($user) === 0) {
                    $validator->errors()->add('company_id', 'A valid company assignment is required before creating attendance or roster rule packs.');

                    return;
                }

                if ($this->filled('company_id') && ! $scope->allowsSettingMutation($user, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'Attendance and roster rules can be created only for your active company scope.');
                }

                $value = $this->input('value');
                if (! is_array($value)) {
                    return;
                }

                try {
                    app(AttendanceRosterRulePackValidator::class)->normalize(
                        (string) $this->input('setting_key'),
                        $value,
                    );
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $field => $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add($field, $message);
                        }
                    }
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function normalizedPayload(): array
    {
        $settingKey = (string) $this->input('setting_key');
        $normalized = app(AttendanceRosterRulePackValidator::class)->normalize(
            $settingKey,
            (array) $this->input('value', []),
        );

        return [
            'company_id' => $this->filled('company_id') ? (int) $this->input('company_id') : null,
            'setting_group' => 'hr',
            'setting_key' => $settingKey,
            'label' => $this->string('label')->toString(),
            'description' => $this->input('description'),
            'value_type' => 'object',
            'value' => $normalized,
            'effective_from' => $this->input('effective_from'),
            'metadata' => [
                'source' => 'people_logic_center',
                'governed_domain' => $settingKey === AttendanceRosterRulePackValidator::ATTENDANCE_KEY
                    ? 'attendance_calculation'
                    : 'roster_operations',
            ],
        ];
    }
}
