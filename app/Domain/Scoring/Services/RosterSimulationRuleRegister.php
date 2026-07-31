<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\RosterSimulationRuleData;
use App\Models\AttendanceRotationRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final readonly class RosterSimulationRuleRegister
{
    public function __construct(
        private CompanyScopeService $companyScope,
        private LogicCenterAccessService $access,
    ) {
    }

    /** @return list<RosterSimulationRuleData> */
    public function forUser(User $user): array
    {
        $capabilities = $this->access->capabilities($user);
        if (! $this->access->canViewSection($user, 'simulation') || ! ($capabilities['manageRosters'] ?? false)) {
            return [];
        }

        return $this->companyScope->apply(
            AttendanceRotationRule::query()
                ->with('employee:id,company_id,employee_code,name')
                ->where('status', 'active')
                ->orderBy('name')
                ->orderByDesc('id'),
            $user,
        )->limit(50)->get()->map(static fn (AttendanceRotationRule $rule): RosterSimulationRuleData => new RosterSimulationRuleData(
            id: (int) $rule->id,
            name: $rule->name,
            employeeName: $rule->employee?->name ?? 'Unavailable employee',
            employeeCode: $rule->employee?->employee_code ?? '-',
            anchorDate: $rule->anchor_date->toDateString(),
            cycleDays: (int) $rule->cycle_days,
            generationHorizonDays: (int) $rule->generation_horizon_days,
            status: $rule->status,
            lockVersion: (int) $rule->lock_version,
        ))->all();
    }
}
