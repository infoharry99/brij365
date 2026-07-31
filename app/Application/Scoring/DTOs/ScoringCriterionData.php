<?php
namespace App\Application\Scoring\DTOs;
final readonly class ScoringCriterionData {
    /** @param list<ScoringConditionData> $conditions */
    public function __construct(
        public string $key,
        public string $label,
        public float $weight,
        public float $maxPoints,
        public string $source,
        public string $normalization,
        public float $inputScaleMin,
        public float $inputScaleMax,
        public bool $required,
        public string $missingDataBehavior,
        public array $conditions,
    ) {}
}
