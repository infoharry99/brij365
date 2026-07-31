<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\ComplianceRuleWorkspaceData;
use App\Domain\Hr\Services\ComplianceRuleRegister;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Http\Requests\Hr\ComplianceRuleSettingIndexRequest;
use App\Models\User;

final class ListComplianceRuleWorkspace
{
    public function __construct(private readonly ComplianceRuleRegister $register) {}

    public function execute(User $actor, array $filters): ComplianceRuleWorkspaceData
    {
        $settingKeys = collect(ComplianceRuleSettingIndexRequest::ALLOWED_SETTING_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => str($key)->replace('.', ' ')->headline()->toString()])
            ->all();
        $settings = $this->register->present($actor, $this->register->all($actor, $filters), $settingKeys);

        return new ComplianceRuleWorkspaceData(
            settings: $settings,
            companies: $this->register->companies($actor),
            settingKeys: $settingKeys,
            summary: $this->register->summary($actor, $filters),
            abilities: [
                'canCreate' => $actor->hasPermission('compliance.manage')
                    || $actor->hasPermission('settings.manage')
                    || $actor->hasPermission(LogicCenterPermissions::STATUTORY_MANAGE),
            ],
        );
    }
}
