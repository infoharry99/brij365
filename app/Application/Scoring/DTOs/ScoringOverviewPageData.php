<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoringOverviewPageData
{
    /**
     * @param array<string, int> $counts
     * @param list<ScoringRuleRowData> $rules
     * @param list<ScoreSnapshotRowData> $snapshots
     * @param list<array<string, string|int>> $recalculationRuns
     * @param list<array<string, string|int>> $recalculationFailures
     * @param array<string, string> $ruleTypes
     * @param array<string, string> $statutoryPackTypes
     * @param list<LogicCenterSectionData> $sections
     * @param list<LogicVariablePackRowData> $variablePacks
     * @param array<string, int> $readiness
     * @param array<string, bool> $capabilities
     * @param list<PerformanceSimulationRuleData> $performanceSimulationRules
     * @param list<RosterSimulationRuleData> $rosterSimulationRules
     */
    public function __construct(
        public string $view,
        public string $title,
        public string $description,
        public array $counts,
        public array $rules,
        public array $snapshots,
        public array $recalculationRuns,
        public array $recalculationFailures,
        public array $ruleTypes,
        public array $statutoryPackTypes,
        public bool $canCreate,
        public array $sections,
        public array $variablePacks,
        public array $readiness,
        public array $capabilities,
        public array $performanceSimulationRules,
        public array $rosterSimulationRules,
    ) {
    }
}
