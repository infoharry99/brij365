<?php

namespace App\Application\Scoring\Actions;

use App\Domain\Scoring\Services\ActiveScoringRuleResolver;
use App\Domain\Scoring\Services\ScoreSnapshotWriter;
use App\Domain\Scoring\Services\StructuredScoreCalculator;
use App\Models\ScoreSnapshot;
use Illuminate\Validation\ValidationException;

final class CalculateAndStoreScore
{
    public function __construct(
        private readonly ActiveScoringRuleResolver $rules,
        private readonly StructuredScoreCalculator $calculator,
        private readonly ScoreSnapshotWriter $snapshots,
    ) {}

    /** @param array<string, mixed> $inputs @param array<string, mixed> $metadata */
    public function handle(int $companyId, string $ruleKey, string $subjectType, int $subjectId, array $inputs, array $metadata = []): ScoreSnapshot
    {
        $rule = $this->rules->resolve($companyId, $ruleKey);
        if (! $rule) {
            throw ValidationException::withMessages(['scoring_rule' => 'No active scoring rule is available for this scoring area.']);
        }

        return $this->snapshots->write($rule, $this->calculator->calculate($rule, $inputs), $subjectType, $subjectId, $inputs, $metadata);
    }
}
