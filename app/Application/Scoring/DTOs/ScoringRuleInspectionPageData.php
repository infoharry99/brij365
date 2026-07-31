<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoringRuleInspectionPageData
{
    /** @param list<array<string, mixed>> $versions @param list<array<string, mixed>> $criteria @param list<array<string, mixed>> $bands @param list<array<string, mixed>> $differences @param list<array<string, mixed>> $activity */
    public function __construct(
        public int $id, public string $name, public string $ruleKey, public int $version, public string $status,
        public string $checksum, public string $effectiveAt, public string $changeReason, public string $createdBy,
        public array $versions, public array $criteria, public array $bands, public array $differences,
        public int $ratingMin, public int $ratingMax,
        public ?int $comparedVersion, public int $eligibleRecords, public int $preservedRecords,
        public string $impactLabel, public array $activity,
    ) {}
}
