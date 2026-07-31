<?php

namespace App\Application\Scoring\DTOs;

final readonly class PerformanceScoreSimulationResultData
{
    /**
     * @param array<string, float> $criterionScores
     * @param array<string, mixed> $componentScores
     * @param array<string, float> $appliedWeights
     * @param list<string> $mandatoryFailures
     */
    public function __construct(
        public int $ruleId,
        public string $ruleName,
        public int $ruleVersion,
        public string $ruleStatus,
        public string $ruleChecksum,
        public float $totalScore,
        public ?string $bandKey,
        public string $bandLabel,
        public bool $passing,
        public bool $pipRecommended,
        public array $criterionScores,
        public array $componentScores,
        public array $appliedWeights,
        public array $mandatoryFailures,
        public string $inputHash,
        public string $resultHash,
    ) {
    }

    /** @return array<string, mixed> */
    public function toView(): array
    {
        return [
            'rule_id' => $this->ruleId,
            'rule_name' => $this->ruleName,
            'rule_version' => $this->ruleVersion,
            'rule_status' => $this->ruleStatus,
            'rule_checksum' => $this->ruleChecksum,
            'total_score' => number_format($this->totalScore, 2, '.', ''),
            'band_key' => $this->bandKey,
            'band_label' => $this->bandLabel,
            'passing' => $this->passing,
            'pip_recommended' => $this->pipRecommended,
            'criterion_scores' => $this->criterionScores,
            'component_scores' => $this->componentScores,
            'applied_weights' => $this->appliedWeights,
            'mandatory_failures' => $this->mandatoryFailures,
            'input_hash' => $this->inputHash,
            'result_hash' => $this->resultHash,
            'mutated_records' => 0,
        ];
    }
}
