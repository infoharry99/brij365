<?php

namespace App\Application\Scoring\Actions;

use App\Application\Scoring\DTOs\PerformanceScoreSimulationInputData;
use App\Application\Scoring\DTOs\PerformanceScoreSimulationResultData;
use App\Domain\Hr\Services\PerformanceScoringEngine;
use App\Models\ScoringRule;

final readonly class SimulatePerformanceScore
{
    public function __construct(private PerformanceScoringEngine $engine)
    {
    }

    public function execute(ScoringRule $rule, PerformanceScoreSimulationInputData $input): PerformanceScoreSimulationResultData
    {
        $result = $this->engine->simulate($rule, $input->criterionScores);
        $configuration = (array) $rule->configuration;
        $band = collect((array) ($configuration['bands'] ?? []))->first(
            static fn (mixed $candidate): bool => is_array($candidate) && ($candidate['key'] ?? null) === $result->scoreBand,
        );
        $passingScore = (float) data_get($configuration, 'thresholds.passing_score', 0);
        $pipScore = (float) data_get($configuration, 'thresholds.pip_score', 0);
        $resultHash = hash('sha256', json_encode([
            'rule_id' => (int) $rule->id,
            'rule_version' => (int) $rule->version,
            'configuration_checksum' => $rule->configuration_checksum,
            'total_score' => $result->totalScore,
            'score_band' => $result->scoreBand,
            'components' => $result->componentScores,
            'weights' => $result->appliedWeights,
            'mandatory_failures' => $result->mandatoryFailures,
        ], JSON_THROW_ON_ERROR));

        return new PerformanceScoreSimulationResultData(
            ruleId: (int) $rule->id,
            ruleName: $rule->name,
            ruleVersion: (int) $rule->version,
            ruleStatus: $rule->status,
            ruleChecksum: $rule->configuration_checksum,
            totalScore: $result->totalScore,
            bandKey: $result->scoreBand,
            bandLabel: (string) ($band['label'] ?? str((string) $result->scoreBand)->headline()),
            passing: $result->totalScore >= $passingScore,
            pipRecommended: $result->totalScore <= $pipScore,
            criterionScores: $input->criterionScores,
            componentScores: $result->componentScores,
            appliedWeights: $result->appliedWeights,
            mandatoryFailures: $result->mandatoryFailures,
            inputHash: $result->inputHash,
            resultHash: $resultHash,
        );
    }
}
