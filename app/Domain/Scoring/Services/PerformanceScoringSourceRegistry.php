<?php

namespace App\Domain\Scoring\Services;

use App\Domain\Scoring\Enums\PerformanceScoringSource;

final class PerformanceScoringSourceRegistry
{
    /** @return list<PerformanceScoringSource> */
    public function all(): array
    {
        return PerformanceScoringSource::cases();
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (PerformanceScoringSource $source): string => $source->value,
            $this->all(),
        );
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return collect($this->all())->mapWithKeys(
            static fn (PerformanceScoringSource $source): array => [$source->value => $source->label()],
        )->all();
    }

    public function supports(string $source): bool
    {
        return PerformanceScoringSource::tryFrom($source) !== null;
    }
}
