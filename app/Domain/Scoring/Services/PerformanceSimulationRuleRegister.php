<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\PerformanceSimulationRuleData;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

final readonly class PerformanceSimulationRuleRegister
{
    public function __construct(
        private CompanyScopeService $companyScope,
        private LogicCenterAccessService $access,
    ) {
    }

    /** @return list<PerformanceSimulationRuleData> */
    public function forUser(User $user): array
    {
        if (! $this->access->canViewSection($user, 'simulation')
            || ! $this->access->capabilities($user)['managePerformance']) {
            return [];
        }

        return $this->companyScope->apply(
            ScoringRule::query()
                ->where('rule_key', 'employee_performance')
                ->whereNotIn('status', ['retired', 'superseded'])
                ->orderByDesc('version'),
            $user,
        )->limit(30)->get()->map(static function (ScoringRule $rule): PerformanceSimulationRuleData {
            $criteria = collect((array) data_get($rule->configuration, 'criteria', []))
                ->filter(static fn (mixed $criterion): bool => is_array($criterion))
                ->map(static fn (array $criterion): array => [
                    'key' => (string) ($criterion['key'] ?? ''),
                    'label' => (string) ($criterion['label'] ?? $criterion['key'] ?? 'Criterion'),
                    'weight' => (float) ($criterion['weight'] ?? 0),
                    'required' => (bool) ($criterion['required'] ?? true),
                    'missing_data_behavior' => (string) ($criterion['missing_data_behavior'] ?? 'block'),
                ])->filter(static fn (array $criterion): bool => $criterion['key'] !== '')
                ->values()->all();

            return new PerformanceSimulationRuleData(
                id: (int) $rule->id,
                name: $rule->name,
                version: (int) $rule->version,
                status: str($rule->status)->headline()->toString(),
                checksum: $rule->configuration_checksum,
                criteria: $criteria,
            );
        })->all();
    }
}
