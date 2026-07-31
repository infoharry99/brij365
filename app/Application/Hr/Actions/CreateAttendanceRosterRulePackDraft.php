<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Domain\Hr\Services\AttendanceRosterRulePackValidator;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingService;

final class CreateAttendanceRosterRulePackDraft
{
    public function __construct(
        private readonly AttendanceRosterRulePackValidator $validator,
        private readonly SystemSettingService $settings,
    ) {}

    public function execute(HrCommandData $command): SystemSetting
    {
        $attributes = $command->attributes;
        $attributes['value'] = $this->validator->normalize(
            (string) ($attributes['setting_key'] ?? ''),
            (array) ($attributes['value'] ?? []),
        );

        return $this->settings->createDraft($attributes, $command->actor, $command->request);
    }
}
