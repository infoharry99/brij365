<?php

namespace App\Domain\Projects\Services;

use App\Application\Projects\Data\ProjectHealthScoreData;
use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Models\Project;
use App\Models\ScoreSnapshot;
use Carbon\CarbonImmutable;

final readonly class ProjectHealthScoreReader
{
    public function __construct(private ActiveScoringRuleResolver $activeRules) {}

    /**
     * @param array<int, int> $projectIds
     * @return array<int, ProjectHealthScoreData>
     */
    public function forProjects(int $companyId, array $projectIds): array
    {
        $rule = $this->activeRules->resolve($companyId, 'project_health');
        if ($rule === null || $projectIds === []) {
            return [];
        }

        return ScoreSnapshot::query()
            ->where('company_id', $companyId)
            ->where('scoring_rule_id', $rule->id)
            ->where('subject_type', Project::class)
            ->whereIn('subject_id', $projectIds)
            ->where('is_current', true)
            ->get()
            ->mapWithKeys(static fn (ScoreSnapshot $snapshot): array => [
                (int) $snapshot->subject_id => new ProjectHealthScoreData(
                    projectId: (int) $snapshot->subject_id,
                    score: number_format((float) $snapshot->total_score, 2),
                    band: (string) $snapshot->score_band,
                    ruleVersion: (int) $snapshot->rule_version,
                    calculatedAt: CarbonImmutable::instance($snapshot->calculated_at),
                    components: $snapshot->component_scores ?? [],
                ),
            ])->all();
    }
}
