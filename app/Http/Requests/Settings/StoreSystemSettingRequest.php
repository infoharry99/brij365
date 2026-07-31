<?php

namespace App\Http\Requests\Settings;

use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Domain\Payroll\Data\StatutoryPayrollCutoverManifest;
use App\Domain\Payroll\Services\StatutoryPayrollCutoverManifestValidator;
use App\Models\SystemSetting;
use App\Services\Crm\LeadQualityScoreService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreSystemSettingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        if (is_string($value) && in_array($this->input('value_type'), ['json', 'object', 'array'], true)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['value' => $decoded]);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', SystemSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'setting_group' => ['required', 'string', 'max:80'],
            'setting_key' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9_.-]+$/'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'value_type' => ['required', 'string', Rule::in(['json', 'object', 'array', 'string', 'integer', 'decimal', 'boolean'])],
            'value' => ['required'],
            'effective_from' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('setting_key') === LeadQualityScoreService::SETTING_KEY) {
                    $this->validateLeadQualityScoreRules($validator);
                }

                if ($this->input('setting_key') === 'collaboration.task_settings') {
                    $this->validateCollaborationTaskSettings($validator);
                }

                if ($this->input('setting_key') === 'hr.custom_mis_reports') {
                    $this->validateHrCustomMisReports($validator);
                }

                if ($this->input('setting_key') === StatutoryPayrollCutoverManifest::SETTING_KEY) {
                    $this->validateStatutoryPayrollCutoverManifest($validator);
                }

                if (in_array($this->input('setting_key'), [
                    AttendanceRosterRulePackValidator::ATTENDANCE_KEY,
                    AttendanceRosterRulePackValidator::ROSTER_KEY,
                ], true)) {
                    $this->validateAttendanceRosterRulePack($validator);
                }

                if (
                    in_array($this->input('value_type'), ['json', 'object', 'array'], true)
                    && is_string($this->input('value'))
                ) {
                    $validator->errors()->add('value', 'JSON, object and array settings must contain valid JSON.');
                }

                $user = $this->user();
                $scope = app(CompanyScopeService::class);
                if (! $user || $scope->settingCompanyIdFor($user) === 0) {
                    $validator->errors()->add('company_id', 'A valid company assignment is required before creating company-scoped settings.');

                    return;
                }

                if ($this->filled('company_id') && ! $scope->allowsSettingMutation($user, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'Settings can be created only for the active company scope.');
                }
            },
        ];
    }

    private function validateStatutoryPayrollCutoverManifest(Validator $validator): void
    {
        if (! in_array($this->input('value_type'), ['json', 'object'], true)) {
            $validator->errors()->add('value_type', 'The statutory payroll cutover manifest must be stored as a JSON/object setting.');
        }

        $value = $this->input('value');
        if (! is_array($value)) {
            $validator->errors()->add('value', 'The statutory payroll cutover manifest must contain a structured object.');

            return;
        }

        try {
            app(StatutoryPayrollCutoverManifestValidator::class)->assertValid($value);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }
    }

    private function validateAttendanceRosterRulePack(Validator $validator): void
    {
        if (! in_array($this->input('value_type'), ['json', 'object'], true)) {
            $validator->errors()->add('value_type', 'Attendance and roster rule packs must be stored as a JSON/object setting.');
        }

        $value = $this->input('value');
        if (! is_array($value)) {
            $validator->errors()->add('value', 'Attendance and roster rule packs must contain a structured object.');

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
    }

    private function validateHrCustomMisReports(Validator $validator): void
    {
        if (! in_array($this->input('value_type'), ['json', 'object'], true)) {
            $validator->errors()->add('value_type', 'HR custom MIS reports must be stored as a JSON/object setting.');
        }

        $value = $this->input('value');
        if (! is_array($value)) {
            $validator->errors()->add('value', 'HR custom MIS reports must contain a report definition object.');

            return;
        }

        foreach (['name', 'report_type'] as $field) {
            if (! is_string($value[$field] ?? null) || trim((string) $value[$field]) === '' || mb_strlen((string) $value[$field]) > 160) {
                $validator->errors()->add("value.{$field}", 'HR custom MIS report name and report type are required and must not exceed 160 characters.');
            }
        }

        if (! is_array($value['filters'] ?? null)) {
            $validator->errors()->add('value.filters', 'HR custom MIS reports must include a filters object.');
        }

        foreach (['columns', 'formats'] as $field) {
            $items = $value[$field] ?? null;
            if (! is_array($items) || $items === []) {
                $validator->errors()->add("value.{$field}", 'HR custom MIS reports must include at least one '.$field.' entry.');

                continue;
            }

            foreach ($items as $index => $item) {
                if (! is_string($item) || trim($item) === '' || mb_strlen($item) > 80) {
                    $validator->errors()->add("value.{$field}.{$index}", 'HR custom MIS columns and formats must be non-empty text values up to 80 characters.');
                }
            }
        }
    }

    private function validateCollaborationTaskSettings(Validator $validator): void
    {
        if (! in_array($this->input('value_type'), ['json', 'object'], true)) {
            $validator->errors()->add('value_type', 'Collaboration task settings must be stored as a JSON/object setting.');
        }

        $value = $this->input('value');
        if (! is_array($value)) {
            $validator->errors()->add('value', 'Collaboration task settings must be an object.');

            return;
        }

        $templates = $value['templates'] ?? [];
        if ($templates === null) {
            return;
        }

        if (! is_array($templates)) {
            $validator->errors()->add('value.templates', 'Task templates must be an array.');

            return;
        }

        if (count($templates) > 50) {
            $validator->errors()->add('value.templates', 'A maximum of 50 task templates is supported per task setting version.');
        }

        $seenTemplateIds = [];
        foreach ($templates as $index => $template) {
            if (! is_array($template)) {
                $validator->errors()->add("value.templates.{$index}", 'Each task template must be an object.');

                continue;
            }

            $id = trim((string) ($template['id'] ?? ''));
            $name = trim((string) ($template['name'] ?? ''));
            $category = trim((string) ($template['cat'] ?? ''));
            $description = trim((string) ($template['desc'] ?? ''));
            $steps = $template['steps'] ?? null;

            if ($id === '' || ! preg_match('/^[a-z0-9_-]+$/', $id)) {
                $validator->errors()->add("value.templates.{$index}.id", 'Template id is required and may contain only lowercase letters, numbers, underscores and hyphens.');
            }

            if ($id !== '' && in_array($id, $seenTemplateIds, true)) {
                $validator->errors()->add("value.templates.{$index}.id", 'Task template ids must be unique.');
            }
            $seenTemplateIds[] = $id;

            if ($name === '' || mb_strlen($name) > 120) {
                $validator->errors()->add("value.templates.{$index}.name", 'Template name is required and must not exceed 120 characters.');
            }

            if ($category === '' || mb_strlen($category) > 80) {
                $validator->errors()->add("value.templates.{$index}.cat", 'Template category is required and must not exceed 80 characters.');
            }

            if ($description !== '' && mb_strlen($description) > 500) {
                $validator->errors()->add("value.templates.{$index}.desc", 'Template description must not exceed 500 characters.');
            }

            if (! is_array($steps) || count($steps) === 0) {
                $validator->errors()->add("value.templates.{$index}.steps", 'Each task template must include at least one step.');

                continue;
            }

            if (count($steps) > 25) {
                $validator->errors()->add("value.templates.{$index}.steps", 'A task template may contain a maximum of 25 steps.');
            }

            foreach ($steps as $stepIndex => $step) {
                $stepLabel = trim((string) $step);

                if ($stepLabel === '' || mb_strlen($stepLabel) > 160) {
                    $validator->errors()->add("value.templates.{$index}.steps.{$stepIndex}", 'Template step labels are required and must not exceed 160 characters.');
                }
            }
        }
    }

    private function validateLeadQualityScoreRules(Validator $validator): void
    {
        if (! in_array($this->input('value_type'), ['json', 'object'], true)) {
            $validator->errors()->add('value_type', 'Lead quality score rules must be stored as a JSON/object setting.');
        }

        $value = $this->input('value');
        if (! is_array($value)) {
            $validator->errors()->add('value', 'Lead quality score rules must contain criteria and bands.');

            return;
        }

        $criteria = $value['criteria'] ?? null;
        if (! is_array($criteria)) {
            $validator->errors()->add('value.criteria', 'Lead quality score rules must include criteria.');

            return;
        }

        if (count($criteria) === 0 || count($criteria) > 8) {
            $validator->errors()->add('value.criteria', 'Lead quality score rules must include between 1 and 8 criteria.');
        }

        foreach ($criteria as $criterionKey => $criterion) {
            $criterionKey = (string) $criterionKey;

            if (! preg_match('/^[a-z][a-z0-9_]{1,39}$/', $criterionKey)) {
                $validator->errors()->add('value.criteria.'.$criterionKey, 'Criterion keys must start with a lowercase letter and may contain lowercase letters, numbers and underscores only.');

                continue;
            }

            if (! is_array($criterion)) {
                $validator->errors()->add('value.criteria.'.$criterionKey, 'Criterion definition must be an object.');

                continue;
            }

            $label = trim((string) ($criterion['label'] ?? ''));
            $maxPoints = $criterion['max_points'] ?? null;
            $options = $criterion['options'] ?? null;

            if ($label === '' || mb_strlen($label) > 120) {
                $validator->errors()->add('value.criteria.'.$criterionKey.'.label', 'Criterion label is required and must not exceed 120 characters.');
            }

            if (! is_numeric($maxPoints) || (int) $maxPoints < 1 || (int) $maxPoints > 100) {
                $validator->errors()->add('value.criteria.'.$criterionKey.'.max_points', 'Criterion max points must be between 1 and 100.');
            }

            if (! is_array($options) || count($options) === 0) {
                $validator->errors()->add('value.criteria.'.$criterionKey.'.options', 'Each quality-score criterion must include at least one selectable condition.');

                continue;
            }

            $seenValues = [];
            foreach ($options as $index => $option) {
                if (! is_array($option)) {
                    $validator->errors()->add("value.criteria.{$criterionKey}.options.{$index}", 'Condition option must be an object.');

                    continue;
                }

                $conditionValue = trim((string) ($option['value'] ?? ''));
                $conditionLabel = trim((string) ($option['label'] ?? ''));
                $points = $option['points'] ?? null;

                if ($conditionValue === '' || ! preg_match('/^[a-z0-9_-]+$/', $conditionValue)) {
                    $validator->errors()->add("value.criteria.{$criterionKey}.options.{$index}.value", 'Condition value is required and may contain only lowercase letters, numbers, underscores and hyphens.');
                }

                if ($conditionValue !== '' && in_array($conditionValue, $seenValues, true)) {
                    $validator->errors()->add("value.criteria.{$criterionKey}.options.{$index}.value", 'Condition values must be unique within each criterion.');
                }
                $seenValues[] = $conditionValue;

                if ($conditionLabel === '' || mb_strlen($conditionLabel) > 120) {
                    $validator->errors()->add("value.criteria.{$criterionKey}.options.{$index}.label", 'Condition label is required and must not exceed 120 characters.');
                }

                if (! is_numeric($points) || (int) $points < 0 || (int) $points > (int) $maxPoints) {
                    $validator->errors()->add("value.criteria.{$criterionKey}.options.{$index}.points", 'Condition points must be between 0 and the criterion max points.');
                }
            }
        }

        $bands = $value['bands'] ?? null;
        if (! is_array($bands) || count($bands) === 0) {
            $validator->errors()->add('value.bands', 'Lead quality score rules must include at least one score band.');

            return;
        }

        $seenBandMinimums = [];
        $hasZeroFloorBand = false;
        foreach ($bands as $index => $band) {
            if (! is_array($band)) {
                $validator->errors()->add("value.bands.{$index}", 'Score band must be an object.');

                continue;
            }

            if (trim((string) ($band['label'] ?? '')) === '') {
                $validator->errors()->add("value.bands.{$index}.label", 'Score band label is required.');
            }

            $minScore = $band['min_score'] ?? null;
            if (! is_numeric($minScore) || (int) $minScore < 0 || (int) $minScore > 100) {
                $validator->errors()->add("value.bands.{$index}.min_score", 'Score band minimum must be between 0 and 100.');
            } else {
                $minScore = (int) $minScore;
                if (in_array($minScore, $seenBandMinimums, true)) {
                    $validator->errors()->add("value.bands.{$index}.min_score", 'Score band minimum scores must be unique.');
                }

                $seenBandMinimums[] = $minScore;
                $hasZeroFloorBand = $hasZeroFloorBand || $minScore === 0;
            }

            if (! in_array($band['status_hint'] ?? null, ['qualified', 'nurture', 'disqualified'], true)) {
                $validator->errors()->add("value.bands.{$index}.status_hint", 'Score band status hint must be qualified, nurture or disqualified.');
            }

            if (! in_array($band['tone'] ?? null, ['green', 'orange', 'red', 'slate', 'blue'], true)) {
                $validator->errors()->add("value.bands.{$index}.tone", 'Score band tone must be one of the supported UI tones.');
            }
        }

        if (! $hasZeroFloorBand) {
            $validator->errors()->add('value.bands', 'Lead quality score rules must include a score band with minimum score 0.');
        }
    }
}
