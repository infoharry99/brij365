<?php
namespace App\Application\Scoring\DTOs;
final readonly class ScoringConditionData {
    public function __construct(public string $key, public string $label, public string $operator, public string $value, public float $points) {}
}
