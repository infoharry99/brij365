<?php
namespace App\Application\Scoring\DTOs;
final readonly class ScoringBandData {
    public function __construct(public string $key, public string $label, public int $minScore, public string $outcome) {}
}
