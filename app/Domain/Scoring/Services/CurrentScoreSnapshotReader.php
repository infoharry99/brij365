<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\CurrentScoreData;
use App\Models\ScoreSnapshot;
use Carbon\CarbonImmutable;

final readonly class CurrentScoreSnapshotReader
{
    public function __construct(private ActiveScoringRuleResolver $activeRules) {}

    /**
     * @param array<int, int> $subjectIds
     * @return array<int, CurrentScoreData>
     */
    public function read(int $companyId, string $ruleKey, string $subjectType, array $subjectIds): array
    {
        $rule = $this->activeRules->resolve($companyId, $ruleKey);
        if ($rule === null || $subjectIds === []) {
            return [];
        }

        return ScoreSnapshot::query()
            ->where('company_id', $companyId)
            ->where('scoring_rule_id', $rule->id)
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
            ->where('is_current', true)
            ->get()
            ->mapWithKeys(static fn (ScoreSnapshot $snapshot): array => [
                (int) $snapshot->subject_id => new CurrentScoreData(
                    subjectId: (int) $snapshot->subject_id,
                    score: number_format((float) $snapshot->total_score, 2),
                    band: (string) $snapshot->score_band,
                    ruleVersion: (int) $snapshot->rule_version,
                    calculatedAt: CarbonImmutable::instance($snapshot->calculated_at),
                    components: $snapshot->component_scores ?? [],
                    metadata: $snapshot->metadata ?? [],
                ),
            ])->all();
    }
}
