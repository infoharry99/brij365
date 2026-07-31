<?php

namespace App\Application\Payroll\Actions;

use App\Application\Payroll\Data\StatutoryPayrollSimulationData;
use App\Domain\Payroll\Services\StatutoryPayrollEngine;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Models\SystemSetting;
use Illuminate\Validation\ValidationException;

final class SimulateStatutoryRulePack
{
    public function __construct(
        private readonly StatutoryRulePackDefinitionValidator $validator,
        private readonly StatutoryPayrollEngine $engine,
    ) {}

    /** @param array<string, mixed> $input */
    public function execute(SystemSetting $setting, array $input): StatutoryPayrollSimulationData
    {
        $definition = (array) $setting->value;
        if (data_get($definition, 'governed_statutory_pack_version') !== StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
            throw ValidationException::withMessages(['setting' => 'Only governed statutory rule packs may use this deterministic simulation.']);
        }

        $this->validator->assertValid($definition);

        return new StatutoryPayrollSimulationData($this->engine->simulate(
            $definition,
            (array) $input['components'],
            (string) $input['statutory_state'],
            (array) ($input['employee_context'] ?? []),
        ));
    }
}
