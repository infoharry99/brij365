<?php
namespace App\Application\Scoring\DTOs;
final readonly class ScoringRuleEditorPageData {
    /** @param list<ScoringCriterionData> $criteria @param list<ScoringBandData> $bands @param array<string, string> $performanceSourceOptions */
    public function __construct(
        public int $id, public string $ruleKey, public string $name, public int $version, public string $status,
        public string $changeReason, public ?string $effectiveAt, public array $criteria, public array $bands,
        public int $ratingMin, public int $ratingMax, public float $passingScore, public float $pipScore,
        public string $roundingMethod, public int $roundingPrecision, public int $minimumSampleSize,
        public bool $overrideAllowed, public bool $overrideReasonRequired,
        public array $performanceSourceOptions,
    ) {}
}
