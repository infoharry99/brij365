<?php

namespace App\Application\Scoring\DTOs;

final readonly class ScoringSubjectData
{
    /** @param array<string, mixed> $inputs @param array<string, mixed> $metadata */
    public function __construct(public string $type, public int $id, public array $inputs, public array $metadata = []) {}
}
